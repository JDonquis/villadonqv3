<script>
    import { onDestroy, onMount } from "svelte";
    import { useForm, router } from "@inertiajs/svelte";
    import Alert from "../../components/Alert.svelte";
    import AsistenciaMatrix from "../../components/AsistenciaMatrix.svelte";
    import StudentObservationsDrawer from "../../components/StudentObservationsDrawer.svelte";
    import { displayAlert } from "../../stores/alertStore";

    export let data = [];

    // View mode: 'notas' | 'asistencia'
    let viewMode = "notas";

    // Drawer de observaciones pedagógicas del estudiante seleccionado
    let observationsStudent = null;
    let showObservations = false;

    // Total de observaciones por estudiante para el contador del botón. Pisa a
    // `student.observations_count` (que trae la matriz) mientras la sesión viva,
    // igual que `editable` / `rasgosEditable` con las notas en borrador.
    let observationCounts = {};
    let lastPlanId = null;

    function obsTotal(student) {
        return observationCounts[student.id] ?? student.observations_count ?? 0;
    }

    function obsLabel(student) {
        const total = obsTotal(student);

        if (total <= 0) {
            return `Observaciones de ${student.last_name}, ${student.name}`;
        }

        return `${total} ${total === 1 ? "observación" : "observaciones"} de ${student.last_name}, ${student.name}`;
    }

    function onObservationsChanged(event) {
        observationCounts = {
            ...observationCounts,
            [event.detail.studentId]: event.detail.total,
        };
    }

    // El plan puede cambiar desde el selector o desde los filtros de período/
    // momento, y todos llegan por `router.get` con `preserveState: true`, así que
    // el componente sobrevive y este estado local hay que limpiarlo con una guarda
    // reactiva (no sirve resetear dentro de selectPlan()).
    $: if ((data.matrix?.plan?.id ?? null) !== lastPlanId) {
        lastPlanId = data.matrix?.plan?.id ?? null;
        observationCounts = {};
    }

    // Grades state
    let editable = {};
    let rasgosEditable = {};
    let selectedVoiceItemId = "";
    let voiceListening = false;
    let voiceStatus = "Selecciona un tema de la tabla y activa el micrófono.";
    let voiceRecognition = null;
    let pendingVoiceStudentId = null;

    function getInitialGrades(matrix) {
        const grades = [];
        matrix?.students?.forEach((student) => {
            matrix.items.forEach((item) => {
                const value = student.scores[item.id];
                grades.push({
                    plan_item_id: item.id,
                    student_id: student.id,
                    score: value != null ? parseFloat(value) : null,
                });
            });
        });
        return grades;
    }

    function getGradesFromEditable() {
        const grades = [];
        data.matrix?.students?.forEach((student) => {
            data.matrix.items.forEach((item) => {
                const value = editable[`${student.id}_${item.id}`];
                grades.push({
                    plan_item_id: item.id,
                    student_id: student.id,
                    score:
                        value === "" || value === null || value === undefined
                            ? null
                            : parseFloat(value),
                });
            });
        });
        return grades;
    }

    function rebuildEditable(matrix) {
        editable = {};
        rasgosEditable = {};
        matrix?.students?.forEach((s) => {
            rasgosEditable[s.id] =
                s.rasgos != null && s.rasgos !== "" ? String(s.rasgos) : "";
            matrix.items.forEach((item) => {
                const key = `${s.id}_${item.id}`;
                const val = s.scores[item.id];
                editable[key] = val != null ? String(val) : "";
            });
        });
    }

    let sortState = {
        key: "student",
        direction: "asc",
    };
    let definitiveByStudent = {};

    function toggleSort(key) {
        if (sortState.key === key) {
            sortState = {
                ...sortState,
                direction: sortState.direction === "asc" ? "desc" : "asc",
            };
            return;
        }

        sortState = {
            key,
            direction: "asc",
        };
    }

    // `state` is passed explicitly: Svelte only tracks variables referenced
    // syntactically in the template, so reading sortState inside the function
    // would leave the indicator without a reactive dependency.
    function getSortIndicator(key, state) {
        if (state.key !== key) return "↕";
        return state.direction === "asc" ? "▲" : "▼";
    }

    $: if (data?.matrix?.plan?.id) rebuildEditable(data.matrix);
    $: if (data?.matrix) {
        // Register reactive deps so the definitive refreshes while typing.
        void editable;
        void rasgosEditable;
        void planRasgosMax;
        definitiveByStudent = {};
        for (const student of data.matrix.students || []) {
            definitiveByStudent[student.id] = computeDefinitive(student);
        }
    }
    $: sortedStudents = [...(data.matrix?.students || [])].sort((a, b) => {
        const direction = sortState.direction === "asc" ? 1 : -1;

        if (sortState.key === "student") {
            const lastNameCompare = (a.last_name || "").localeCompare(
                b.last_name || "",
                "es",
                { sensitivity: "base" },
            );
            if (lastNameCompare !== 0) return lastNameCompare * direction;
            return (
                (a.name || "").localeCompare(b.name || "", "es", {
                    sensitivity: "base",
                }) * direction
            );
        }

        if (sortState.key === "definitive") {
            const aVal = definitiveByStudent[a.id];
            const bVal = definitiveByStudent[b.id];
            if (aVal === null && bVal === null) return 0;
            if (aVal === null) return 1 * direction;
            if (bVal === null) return -1 * direction;
            return (aVal - bVal) * direction;
        }

        const itemId = Number(sortState.key.replace("item_", ""));
        const aVal = a.scores?.[itemId] ?? null;
        const bVal = b.scores?.[itemId] ?? null;

        if (aVal === null && bVal === null) return 0;
        if (aVal === null) return 1 * direction;
        if (bVal === null) return -1 * direction;

        return (Number(aVal) - Number(bVal)) * direction;
    });

    let form = useForm({
        plan_id: data.selected_plan_id || "",
        grades: getInitialGrades(data.matrix),
        rasgos: [],
    });

    $: canPublish = data.matrix?.grade_state?.can_publish === true;
    $: planRasgosMax = data.matrix?.plan?.rasgos_points || 0;
    // El personal de administración califica sobre planes de los profesores.
    $: managesAll = data.manages_all === true;
    $: planTeacherName = data.matrix?.plan?.teacher_name || null;

    function updateGrade(key, value) {
        editable[key] = value;
        editable = { ...editable };
        $form.grades = getGradesFromEditable();
    }

    function letterForScore(value) {
        if (value === "" || value === null || value === undefined) return "";
        const score = Number(value);
        if (!Number.isFinite(score) || score < 10) return "";
        if (score >= 18) return "A";
        if (score >= 15) return "B";
        return "C";
    }

    function scoreForLetter(letter) {
        return { A: "20", B: "18", C: "15" }[letter] ?? "";
    }

    // Formatea la nota con cero inicial (01, 02, ..., 09).
    // Nota 0 se muestra como la raya gris "—" del placeholder.
    function formatGrade(value) {
        if (value === "" || value === null || value === undefined) return "";
        const n = Number(value);
        if (!Number.isFinite(n)) return "";
        if (n <= 0) return "";
        return String(n).padStart(2, "0");
    }

    // Cuántos estudiantes tienen nota > 0 por cada tema (vive de `editable` para reflejar
    // el estado en tiempo real, no el del servidor).
    function getCorregidoInfo() {
        const counts = {};
        const students = data.matrix?.students || [];
        const total = students.length;
        (data.matrix?.items || []).forEach((item) => {
            const positive = students.filter((student) => {
                const val = editable[`${student.id}_${item.id}`];
                const n = Number(val);
                return Number.isFinite(n) && n > 0;
            }).length;
            counts[item.id] = positive;
        });
        return { counts, total };
    }

    $: corregidoInfo = (() => {
        const { counts, total } = getCorregidoInfo();
        return {
            counts,
            total,
            isAllCorrected: (id) => (counts[id] || 0) === total,
        };
    })();

    function normalizeVoiceText(value) {
        return String(value || "")
            .toLocaleLowerCase("es-VE")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9.,\s]/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function levenshteinDistance(a, b) {
        const matrix = Array.from({ length: a.length + 1 }, () =>
            Array(b.length + 1).fill(0),
        );

        for (let i = 0; i <= a.length; i += 1) matrix[i][0] = i;
        for (let j = 0; j <= b.length; j += 1) matrix[0][j] = j;

        for (let i = 1; i <= a.length; i += 1) {
            for (let j = 1; j <= b.length; j += 1) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                matrix[i][j] = Math.min(
                    matrix[i - 1][j] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j - 1] + cost,
                );
            }
        }

        return matrix[a.length][b.length];
    }

    function tokenSimilarity(wordA, wordB) {
        if (!wordA || !wordB) return 0;

        const a = normalizeVoiceText(wordA);
        const b = normalizeVoiceText(wordB);
        if (!a || !b) return 0;

        if (a === b) return 1;
        if (a.startsWith(b) || b.startsWith(a)) return 0.8;

        const phoneticA = a.replace(/[aeiou]/g, "");
        const phoneticB = b.replace(/[aeiou]/g, "");
        if (phoneticA === phoneticB) return 0.75;

        const distance = levenshteinDistance(a, b);
        const maxLen = Math.max(a.length, b.length, 1);
        const ratio = 1 - distance / maxLen;
        if (ratio > 0.75) return ratio;

        return 0;
    }

    function findVoiceStudent(transcript) {
        const transcriptText = normalizeVoiceText(transcript);
        const words = transcriptText
            .split(" ")
            .filter(
                (word) =>
                    ![
                        "estudiante",
                        "alumno",
                        "alumna",
                        "nota",
                        "para",
                        "de",
                        "a",
                    ].includes(word),
            );
        if (!words.length) return { student: null, ambiguous: false };

        const matches = (data.matrix?.students || [])
            .map((student) => {
                const lastNameParts = student.last_name
                    .split(" ")
                    .filter(Boolean);
                const firstLastName = normalizeVoiceText(
                    lastNameParts[0] || "",
                );
                const secondLastName = normalizeVoiceText(
                    lastNameParts[1] || "",
                );
                const firstName = normalizeVoiceText(
                    student.name.split(" ")[0] || "",
                );
                const secondName = normalizeVoiceText(
                    student.name.split(" ")[1] || "",
                );

                // ===== MATCHING DE APELLIDO =====
                let firstLastNameScore = 0;
                let secondLastNameScore = 0;

                if (firstLastName) {
                    words.forEach((word) => {
                        const sim = tokenSimilarity(word, firstLastName);
                        if (sim > firstLastNameScore) firstLastNameScore = sim;
                    });
                }

                if (secondLastName) {
                    words.forEach((word) => {
                        const sim = tokenSimilarity(word, secondLastName);
                        if (sim > secondLastNameScore)
                            secondLastNameScore = sim;
                    });
                }

                // ===== MATCHING DE NOMBRE =====
                let firstNameScore = 0;
                let secondNameScore = 0;

                if (firstName) {
                    words.forEach((word) => {
                        const sim = tokenSimilarity(word, firstName);
                        if (sim > firstNameScore) firstNameScore = sim;
                    });
                }

                if (secondName) {
                    words.forEach((word) => {
                        const sim = tokenSimilarity(word, secondName);
                        if (sim > secondNameScore) secondNameScore = sim;
                    });
                }

                // ===== COMBINACIÓN CON PESOS =====
                const hasFirstLastName = firstLastNameScore > 0.7;
                const hasFirstName = firstNameScore > 0.7;
                const hasBoth = hasFirstName && hasFirstLastName;

                // Score base: apellido pesa 3, nombre pesa 1
                let score = firstLastNameScore * 3.0 + firstNameScore * 1.0;

                // Bonus fuerte si matchea nombre + apellido paterno
                if (hasBoth) {
                    score += 2.0;
                }

                // Bonus por segundo apellido
                if (secondLastNameScore > 0.7) {
                    score += secondLastNameScore * 1.5;
                }

                // Bonus por segundo nombre
                if (secondNameScore > 0.7) {
                    score += secondNameScore * 0.5;
                }

                // ✅ CORREGIDO: Solo penalizar si hay un apellido en el dictado
                // y NO matchea, pero el nombre sí. Si no se dictó apellido,
                // no penalizar.
                const transcriptHasLastName = words.some((word) => {
                    const allLastNames = [
                        normalizeVoiceText(firstLastName),
                        normalizeVoiceText(secondLastName),
                    ].filter(Boolean);
                    return allLastNames.some(
                        (lastName) => tokenSimilarity(word, lastName) > 0.7,
                    );
                });

                if (
                    transcriptHasLastName &&
                    !hasFirstLastName &&
                    hasFirstName
                ) {
                    score *= 0.4;
                }

                // Bonus extra si el apellido es EXACTO
                if (firstLastName && words.includes(firstLastName)) {
                    score += 2.0;
                }

                return { student, score };
            })
            .filter((entry) => entry.score > 0.3) // ✅ Bajado de 0.5 a 0.3
            .sort((a, b) => b.score - a.score);

        if (!matches.length) return { student: null, ambiguous: false };

        const bestScore = matches[0].score;

        if (matches.length === 1) {
            return { student: matches[0].student, ambiguous: false };
        }

        const secondBest = matches[1].score;

        // ✅ Umbral de ambigüedad más relajado
        const ambiguityThreshold = 0.85;

        if (secondBest >= bestScore * ambiguityThreshold) {
            const exactLastNameMatches = matches.filter(
                (m) =>
                    m.score >= bestScore * 0.9 &&
                    m.student.last_name
                        .toLowerCase()
                        .normalize("NFD")
                        .replace(/[\u0300-\u036f]/g, "")
                        .split(" ")[0]
                        .includes(words.find((w) => w.length > 3) || ""),
            );

            if (exactLastNameMatches.length === 1) {
                return {
                    student: exactLastNameMatches[0].student,
                    ambiguous: false,
                };
            }

            return { student: null, ambiguous: true };
        }

        return { student: matches[0].student, ambiguous: false };
    }

    // ===== PARSEAR NOTA DE VOZ =====
    function parseVoiceScore(transcript) {
        const normalized = normalizeVoiceText(transcript);

        // 1. Buscar número con dígitos (ej: "15", "11,5", "18.5")
        const digitMatch = normalized.match(
            /(?:^|\s)(\d{1,2}(?:[.,]\d+)?)(?:\s|$)/,
        );
        if (digitMatch) {
            const num = Number(digitMatch[1].replace(",", "."));
            if (num >= 0 && num <= 20) return num;
        }

        // 2. Diccionario de números en palabras
        const numberWords = {
            cero: 0,
            un: 1,
            uno: 1,
            una: 1,
            dos: 2,
            tres: 3,
            cuatro: 4,
            cinco: 5,
            seis: 6,
            siete: 7,
            ocho: 8,
            nueve: 9,
            diez: 10,
            once: 11,
            doce: 12,
            trece: 13,
            catorce: 14,
            quince: 15,
            dieciseis: 16,
            diecisiete: 17,
            dieciocho: 18,
            diecinueve: 19,
            veinte: 20,
        };

        const words = normalized.split(" ");

        // 3. Buscar "veinte" + "uno/dos/tres..." (veintiuno, veintidos, etc.)
        const veinteIndex = words.indexOf("veinte");
        if (veinteIndex !== -1 && veinteIndex < words.length - 1) {
            const nextWord = words[veinteIndex + 1];
            if (Object.hasOwn(numberWords, nextWord)) {
                const base = numberWords[nextWord];
                let total = 20 + base;

                // Verificar si hay decimal: "veintiuno coma cinco"
                const comaIndex = words.indexOf("coma", veinteIndex + 2);
                const puntoIndex = words.indexOf("punto", veinteIndex + 2);
                const separatorIndex =
                    comaIndex !== -1 ? comaIndex : puntoIndex;

                if (separatorIndex !== -1) {
                    const decimalWord = words[separatorIndex + 1];
                    if (
                        decimalWord &&
                        Object.hasOwn(numberWords, decimalWord)
                    ) {
                        total += numberWords[decimalWord] / 10;
                    }
                }

                if (total <= 20) return total;
            }
        }

        // 4. Buscar palabra de número
        const scoreWord = words.find((word) =>
            Object.hasOwn(numberWords, word),
        );
        if (scoreWord === undefined) return null;

        let score = numberWords[scoreWord];
        const scoreIndex = words.indexOf(scoreWord);

        // 5. Verificar decimal: "quince coma cinco" o "quince punto cinco"
        const separatorIndex = words.findIndex(
            (word, index) =>
                index > scoreIndex && ["coma", "punto"].includes(word),
        );
        if (separatorIndex !== -1) {
            const decimalWord = words[separatorIndex + 1];
            if (decimalWord && Object.hasOwn(numberWords, decimalWord)) {
                score += numberWords[decimalWord] / 10;
            }
        } else if (words.includes("medio") && score < 20) {
            score += 0.5;
        }

        return score;
    }

    // ✅ NUEVA FUNCIÓN: Dividir transcript en comandos individuales
    function splitTranscriptIntoCommands(transcript) {
        const normalized = transcript
            .toLocaleLowerCase("es-VE")
            .replace(/\s+/g, " ")
            .trim();

        const numberWords = [
            "cero",
            "un",
            "uno",
            "una",
            "dos",
            "tres",
            "cuatro",
            "cinco",
            "seis",
            "siete",
            "ocho",
            "nueve",
            "diez",
            "once",
            "doce",
            "trece",
            "catorce",
            "quince",
            "dieciseis",
            "diecisiete",
            "dieciocho",
            "diecinueve",
            "veinte",
        ];

        const decimalPattern = `(\\d{1,2}(?:[.,]\\d+)?(?:\\s+(?:coma|punto)\\s+\\d{1,2})?|\\b(?:${numberWords.join("|")})\\b(?:\\s+(?:coma|punto)\\s+\\b(?:${numberWords.join("|")})\\b)?)`;
        const regex = new RegExp(decimalPattern, "gi");

        const commands = [];
        let lastIndex = 0;
        let match;

        while ((match = regex.exec(normalized)) !== null) {
            const numberEnd = match.index + match[0].length;
            const commandText = normalized.slice(lastIndex, numberEnd).trim();

            if (commandText.length >= 3) {
                commands.push(commandText);
            }

            lastIndex = numberEnd;
        }

        const remaining = normalized.slice(lastIndex).trim();
        if (remaining.length > 0) {
            commands.push(remaining);
        }

        if (commands.length === 0) {
            commands.push(normalized);
        }

        return commands;
    }

    // ✅ NUEVA FUNCIÓN: Reproducir sonidos con Web Audio API
    function playSound(type) {
        try {
            const AudioContext =
                window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            const ctx = new AudioContext();
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);

            if (type === "completo") {
                // Fanfarria de 3 notas
                oscillator.type = "sine";
                oscillator.frequency.setValueAtTime(523.25, ctx.currentTime);
                oscillator.frequency.setValueAtTime(
                    659.25,
                    ctx.currentTime + 0.1,
                );
                oscillator.frequency.setValueAtTime(
                    783.99,
                    ctx.currentTime + 0.2,
                );
                gainNode.gain.setValueAtTime(0.15, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(
                    0.001,
                    ctx.currentTime + 0.4,
                );
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.4);
            } else if (type === "nota") {
                oscillator.type = "sine";
                oscillator.frequency.setValueAtTime(523.25, ctx.currentTime);
                oscillator.frequency.setValueAtTime(
                    659.25,
                    ctx.currentTime + 0.1,
                );
                gainNode.gain.setValueAtTime(0.15, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(
                    0.001,
                    ctx.currentTime + 0.25,
                );
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.25);
            } else if (type === "estudiante") {
                oscillator.type = "sine";
                oscillator.frequency.setValueAtTime(440, ctx.currentTime);
                oscillator.frequency.setValueAtTime(
                    554.37,
                    ctx.currentTime + 0.08,
                );
                gainNode.gain.setValueAtTime(0.12, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(
                    0.001,
                    ctx.currentTime + 0.2,
                );
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.2);
            } else if (type === "error") {
                oscillator.type = "square";
                oscillator.frequency.setValueAtTime(300, ctx.currentTime);
                oscillator.frequency.setValueAtTime(
                    200,
                    ctx.currentTime + 0.15,
                );
                gainNode.gain.setValueAtTime(0.08, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(
                    0.001,
                    ctx.currentTime + 0.25,
                );
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.25);
            } else if (type === "casi") {
                oscillator.type = "triangle";
                oscillator.frequency.setValueAtTime(600, ctx.currentTime);
                oscillator.frequency.setValueAtTime(
                    750,
                    ctx.currentTime + 0.08,
                );
                gainNode.gain.setValueAtTime(0.12, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(
                    0.001,
                    ctx.currentTime + 0.2,
                );
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.2);
            }

            setTimeout(() => ctx.close(), 500);
        } catch (e) {
            // Silencioso si el navegador no lo soporta
        }
    }

    // ✅ NUEVO: Estado para animación del banner
    let voiceFlashType = "";
    let voiceFlashTimer = null;

    function triggerVoiceFlash(type) {
        voiceFlashType = type;
        if (voiceFlashTimer) clearTimeout(voiceFlashTimer);
        voiceFlashTimer = setTimeout(() => {
            voiceFlashType = "";
        }, 700);
    }

    let voiceFocusTimer = null;

    function focusVoiceGrade(studentId, force = false) {
        if (voiceFocusTimer) {
            clearTimeout(voiceFocusTimer);
            voiceFocusTimer = null;
        }

        if (!force) {
            voiceFocusTimer = setTimeout(() => {
                if (pendingVoiceStudentId !== studentId) return;

                const input = document.querySelector(
                    `[data-grade-input="${studentId}_${selectedVoiceItemId}"]`,
                );
                input?.focus();
                input?.select();
                voiceFocusTimer = null;
            }, 220);
            return;
        }

        const input = document.querySelector(
            `[data-grade-input="${studentId}_${selectedVoiceItemId}"]`,
        );
        input?.focus();
        input?.select();
    }

    function processVoiceTranscript(transcript) {
        const score = parseVoiceScore(transcript);

        if (pendingVoiceStudentId !== null) {
            if (score === null) {
                voiceStatus = `No reconocí la nota. Diga un número del 0 al 20.`;
                playSound("error");
                triggerVoiceFlash("error");
                return;
            }
            if (score < 0 || score > 20) {
                voiceStatus =
                    "La nota debe estar entre 0 y 20. Diga el número nuevamente.";
                playSound("error");
                triggerVoiceFlash("error");
                return;
            }

            const studentId = pendingVoiceStudentId;
            if (voiceFocusTimer) {
                clearTimeout(voiceFocusTimer);
                voiceFocusTimer = null;
            }
            updateGrade(`${studentId}_${selectedVoiceItemId}`, String(score));
            focusVoiceGrade(studentId, true);
            pendingVoiceStudentId = null;
            voiceStatus = `Nota ${score} registrada. Diga el nombre del siguiente estudiante.`;

            // ✅ Sonido y animación de nota guardada
            playSound("nota");
            triggerVoiceFlash("nota");
            showVoiceProgressBanner();
            return;
        }

        if (score !== null) {
            const { student } = findVoiceStudent(
                transcript.replace(/\b(\d{1,2}(?:[.,]\d+)?)\b/g, "").trim(),
            );
            if (student) {
                pendingVoiceStudentId = student.id;
                focusVoiceGrade(student.id);
                updateGrade(
                    `${student.id}_${selectedVoiceItemId}`,
                    String(score),
                );
                focusVoiceGrade(student.id, true);
                voiceStatus = `Nota ${score} registrada para ${student.name} ${student.last_name}. Diga el nombre del siguiente estudiante.`;
                pendingVoiceStudentId = null;

                // ✅ Sonido y animación de nota guardada
                playSound("nota");
                triggerVoiceFlash("nota");
                showVoiceProgressBanner();
                return;
            }
        }

        const { student, ambiguous } = findVoiceStudent(transcript);
        if (!student) {
            voiceStatus = ambiguous
                ? `Hay varios estudiantes que coinciden con «${transcript}». Diga también el apellido.`
                : `No encontré a «${transcript}». Diga su nombre y/o apellido.`;
            playSound("error");
            triggerVoiceFlash("error");
            return;
        }

        pendingVoiceStudentId = student.id;
        focusVoiceGrade(student.id);
        voiceStatus = `Ahora diga la nota.`;

        // ✅ Sonido y animación de estudiante detectado
        playSound("estudiante");
        triggerVoiceFlash("estudiante");
    }

    function stopVoiceDictation() {
        const recognition = voiceRecognition;
        voiceRecognition = null;
        voiceListening = false;
        if (recognition) {
            try {
                recognition.stop();
            } catch (error) {
                // The browser may already have stopped recognition.
            }
        }
    }

    /**
     * Abre el drawer de observaciones del estudiante. Se corta el dictado de notas
     * para no dejar dos sesiones de micrófono activas a la vez.
     */
    function openObservations(student) {
        stopVoiceDictation();
        resetVoiceSelection();
        observationsStudent = student;
        showObservations = true;
    }

    onMount(() => {
        const handleKeyboardShortcut = (event) => {
            const target = event.target;
            const isTypingField =
                target instanceof HTMLElement &&
                ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName);

            const isShortcut =
                (event.ctrlKey || event.metaKey) &&
                !event.altKey &&
                !event.shiftKey &&
                event.key.toLowerCase() === "m";

            if (!isShortcut || isTypingField) return;

            event.preventDefault();
            if (!selectedVoiceItemId) {
                voiceStatus =
                    "Primero selecciona el tema en el encabezado de su columna.";
                return;
            }

            toggleVoiceDictation();
        };

        window.addEventListener("keydown", handleKeyboardShortcut);
        return () =>
            window.removeEventListener("keydown", handleKeyboardShortcut);
    });

    function resetVoiceSelection() {
        stopVoiceDictation();
        selectedVoiceItemId = "";
        pendingVoiceStudentId = null;
        voiceStatus = "Selecciona un tema de la tabla y activa el micrófono.";
    }

    let voiceProcessingStatus = "";
    let voiceInterimText = "";

    function toggleVoiceDictation() {
        if (voiceListening) {
            stopVoiceDictation();
            voiceStatus = "Dictado pausado.";
            return;
        }
        if (!selectedVoiceItemId) {
            voiceStatus =
                "Primero selecciona el tema en el encabezado de su columna.";
            return;
        }

        const SpeechRecognition =
            window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            voiceStatus =
                "Este navegador no admite dictado por voz. Prueba con Chrome o Edge.";
            return;
        }

        const recognition = new SpeechRecognition();
        recognition.lang = "es-VE";
        recognition.continuous = true;
        recognition.interimResults = true;
        voiceRecognition = recognition;
        voiceListening = true;
        voiceStatus = "Escuchando: diga el nombre del estudiante.";

        recognition.onresult = (event) => {
            for (
                let index = event.resultIndex;
                index < event.results.length;
                index += 1
            ) {
                const transcript = event.results[index][0].transcript;
                const isFinal = event.results[index].isFinal;

                if (isFinal) {
                    voiceInterimText = "";
                    const commands = splitTranscriptIntoCommands(transcript);

                    if (commands.length > 1) {
                        voiceProcessingStatus = `Guardando ${commands.length} notas...`;
                        triggerVoiceFlash("procesando");
                    }

                    commands.forEach((command) => {
                        if (command.trim().length >= 1) {
                            processVoiceTranscript(command);
                        }
                    });

                    voiceProcessingStatus = "";
                } else {
                    // ✅ Solo mostrar, no procesar
                    voiceInterimText = transcript;
                }
            }
        };

        recognition.onerror = (event) => {
            voiceListening = false;
            voiceRecognition = null;
            voiceStatus =
                event.error === "not-allowed"
                    ? "Permite el acceso al micrófono en el navegador para dictar notas."
                    : `Error de dictado: ${event.error}. Puedes volver a activar el micrófono.`;
        };
        recognition.onend = () => {
            if (voiceRecognition === recognition) {
                voiceRecognition = null;
                voiceListening = false;
                if (pendingVoiceStudentId !== null) {
                    voiceStatus +=
                        " Activa nuevamente el micrófono para dictar la nota pendiente.";
                }
            }
        };

        try {
            recognition.start();
        } catch (error) {
            voiceRecognition = null;
            voiceListening = false;
            voiceStatus =
                "No se pudo iniciar el micrófono. Inténtalo nuevamente.";
        }
    }

    // ===== SISTEMA DE SONIDOS =====

    onDestroy(stopVoiceDictation);

    function updateRasgos(studentId, value) {
        rasgosEditable[studentId] = value;
        rasgosEditable = { ...rasgosEditable };
        const planId = data.matrix?.plan?.id;
        $form.rasgos = (data.matrix?.students || []).map((s) => {
            const raw = rasgosEditable[s.id];
            return {
                plan_id: planId,
                student_id: s.id,
                rasgos_score:
                    raw === "" || raw === null || raw === undefined
                        ? null
                        : parseInt(raw, 10),
            };
        });
    }

    function getActiveMomentId(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";

        const today = new Date().toISOString().slice(0, 10);
        const active = schoolLapse.lapses.find(
            (lap) => today >= (lap.start || "") && today <= (lap.end || ""),
        );

        return String(
            active?.id ??
                schoolLapse.lapses[schoolLapse.lapses.length - 1]?.id ??
                "",
        );
    }

    $: selectedSchoolLapse =
        (data.school_lapses || []).find(
            (l) => String(l.id) === String(data.school_lapse_id),
        ) ||
        (data.school_lapses || [])[0] ||
        null;
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    // Store the currently selected moment. We default to the server value
    // / active-by-date only when the user hasn't made a manual selection.
    let selectedLapseId = "";
    let userSelectedLapse = false;

    $: if (!userSelectedLapse) {
        const serverLapse = String(
            data.lapse_id || getActiveMomentId(selectedSchoolLapse) || "",
        );
        if (selectedLapseId !== serverLapse) selectedLapseId = serverLapse;
    }

    function selectSchoolLapse(schoolLapseId) {
        resetVoiceSelection();
        const nextSchool = (data.school_lapses || []).find(
            (l) => String(l.id) === String(schoolLapseId),
        );
        const nextMoment = getActiveMomentId(nextSchool);
        // Reset user selection when switching school lapse so the new
        // period's default moment can be applied.
        userSelectedLapse = false;
        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: schoolLapseId,
                lapse_id: nextMoment,
                plan_id: "",
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function selectMoment(momentId) {
        resetVoiceSelection();
        // Mark that the user explicitly chose a moment so we don't
        // overwrite their selection with the date-based default.
        userSelectedLapse = true;
        if (selectedLapseId !== momentId) selectedLapseId = momentId;

        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: data.school_lapse_id || "",
                lapse_id: momentId,
                plan_id: "",
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function selectPlan(planId) {
        resetVoiceSelection();
        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: data.school_lapse_id || "",
                lapse_id: data.lapse_id || selectedLapseId || "",
                plan_id: planId,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function formatTooltipDate(date) {
        if (!date) return "—";

        const parsed = new Date(`${date}T00:00:00`);
        if (Number.isNaN(parsed.getTime())) return "—";

        return parsed.toLocaleDateString("es-VE", {
            weekday: "short",
            day: "numeric",
            month: "short",
        });
    }

    function getUnitMetaForItem(item) {
        const unit = (data.matrix?.units || []).find((entry) =>
            (entry.topics || []).some(
                (topic) =>
                    String(topic.id) === `topic_${item.id}` ||
                    (topic.name === item.name &&
                        Number(topic.percentage) === Number(item.percentage)),
            ),
        );

        return {
            unit_number: unit?.unit_number ?? item.unit_number ?? 1,
            unit_name: unit?.name ?? item.unit_name ?? "Sin unidad",
            assessment_type:
                unit?.topics?.find(
                    (topic) =>
                        String(topic.id) === `topic_${item.id}` ||
                        (topic.name === item.name &&
                            Number(topic.percentage) ===
                                Number(item.percentage)),
                )?.assessment_type ??
                item.assessment_type ??
                "—",
            scheduled_date:
                unit?.topics?.find(
                    (topic) =>
                        String(topic.id) === `topic_${item.id}` ||
                        (topic.name === item.name &&
                            Number(topic.percentage) ===
                                Number(item.percentage)),
                )?.scheduled_date ??
                item.scheduled_date ??
                null,
        };
    }

    function computeDefinitive(student) {
        if (!data.matrix) return null;

        let total = 0;
        for (const item of data.matrix.items) {
            const val = editable[`${student.id}_${item.id}`];
            if (val === "" || val === null || val === undefined) return null;
            const num = parseFloat(val);
            if (isNaN(num)) return null;
            total += num * (item.percentage / 100);
        }

        if (planRasgosMax > 0) {
            const raw = rasgosEditable[student.id];
            if (raw === "" || raw === null || raw === undefined) return null;
            const rasgosNum = parseFloat(raw);
            if (isNaN(rasgosNum)) return null;
            total += rasgosNum;
        }

        return Math.round(total * 100) / 100;
    }

    function getDefinitiveMedal(student) {
        if (!data.matrix) return null;

        const studentScore = definitiveByStudent[student.id];
        if (studentScore === null || studentScore < 10) return null;

        const scores = data.matrix.students
            .map((entry) => ({
                id: entry.id,
                score: definitiveByStudent[entry.id],
            }))
            .filter((entry) => entry.score !== null && entry.score >= 10)
            .sort((a, b) => b.score - a.score);

        if (!scores.length) return null;

        const uniqueScores = [...new Set(scores.map((entry) => entry.score))];
        const medalByScore = new Map();

        uniqueScores.forEach((score, index) => {
            if (index === 0) medalByScore.set(score, "gold");
            else if (index === 1) medalByScore.set(score, "silver");
            else if (index === 2) medalByScore.set(score, "bronze");
        });

        return medalByScore.get(studentScore) ?? null;
    }

    function getMedalIcon(medal) {
        if (medal === "gold")
            return {
                icon: "fluent-emoji-flat:1st-place-medal",
                class: "text-2xl",
            };
        if (medal === "silver")
            return {
                icon: "fluent-emoji-flat:2nd-place-medal",
                class: "text-xl",
            };
        if (medal === "bronze")
            return {
                icon: "fluent-emoji-flat:3rd-place-medal",
                class: "text-xl",
            };
        return "";
    }

    function handleSave() {
        if (!data.matrix) return;

        const grades = [];
        data.matrix.students.forEach((s) => {
            data.matrix.items.forEach((item) => {
                const val = editable[`${s.id}_${item.id}`];
                grades.push({
                    plan_item_id: item.id,
                    student_id: s.id,
                    score:
                        val === "" || val === null || val === undefined
                            ? null
                            : parseFloat(val),
                });
            });
        });

        const rasgos = [];
        data.matrix.students.forEach((s) => {
            const raw = rasgosEditable[s.id];
            rasgos.push({
                student_id: s.id,
                rasgos_score:
                    raw === "" || raw === null || raw === undefined
                        ? null
                        : parseInt(raw, 10),
            });
        });

        $form.clearErrors();
        $form.plan_id = data.matrix.plan.id;
        $form.grades = grades;
        $form.rasgos = rasgos;
        $form.post("/dashboard/mis-estudiantes/guardar-notas", {
            preserveScroll: true,
            onSuccess: () => {
                $form.defaults();
                $form.reset();
                displayAlert({
                    type: "success",
                    message: "Notas guardadas correctamente",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al guardar las notas",
                });
            },
        });
    }

    function handlePublish() {
        if (!data.matrix?.plan?.id || !canPublish) return;

        $form.plan_id = data.matrix.plan.id;
        $form.post("/dashboard/mis-estudiantes/publicar-notas", {
            preserveScroll: true,
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Notas publicadas correctamente",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al publicar las notas",
                });
            },
        });
    }

    let voiceProgressVisible = false;
    let voiceProgressMessage = "";
    let voiceProgressType = "progreso"; // "progreso" | "casi" | "completo"
    let voiceProgressTimer = null;

    function showVoiceProgressBanner() {
        if (!selectedVoiceItemId) return;

        const { counts, total } = getCorregidoInfo();
        const corrected = counts[selectedVoiceItemId] || 0;
        if (corrected === 0) return;

        const percentage = (corrected / total) * 100;
        const remaining = total - corrected;

        if (corrected === total) {
            voiceProgressMessage = "¡Todos corregidos!";
            voiceProgressType = "completo";
            playSound("completo");
            stopVoiceDictation();
        } else if (percentage >= 65) {
            voiceProgressMessage = `¡Solo faltan ${remaining}!`;
            voiceProgressType = "casi";
            playSound("casi");
        } else {
            voiceProgressMessage = `Corregidos: ${corrected}/${total}`;
            voiceProgressType = "progreso";
        }

        voiceProgressVisible = true;

        if (voiceProgressTimer) clearTimeout(voiceProgressTimer);
        voiceProgressTimer = setTimeout(() => {
            voiceProgressVisible = false;
        }, 2500);
    }

    let lastProgressItemId = null;

    $: if (selectedVoiceItemId && selectedVoiceItemId !== lastProgressItemId) {
        // Solo resetear cuando REALMENTE cambia el item
        lastProgressItemId = selectedVoiceItemId;
        voiceProgressVisible = false;
        if (voiceProgressTimer) {
            clearTimeout(voiceProgressTimer);
            voiceProgressTimer = null;
        }
    }
</script>

<svelte:head>
    <title>Mis Estudiantes</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden">
        Mis Estudiantes
    </h2>
</div>

<div
    class="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex flex-col md:flex-row flex-wrap md:items-center gap-5 md:gap-6"
>
    <div class="flex flex-col md:flex-row md:items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Período escolar
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(data.school_lapse_id || "")}
            on:change={(e) => selectSchoolLapse(e.target.value)}
        >
            {#each data.school_lapses || [] as lapse}
                <option value={String(lapse.id)}>{lapse.label}</option>
            {/each}
        </select>
    </div>

    <div class="flex flex-col md:flex-row md:items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Momento escolar
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={selectedLapseId}
            on:change={(e) => selectMoment(e.target.value)}
        >
            {#each momentOptions as lap}
                <option value={String(lap.id)}>{lap.label}</option>
            {/each}
        </select>
    </div>

    <div class="flex-col md:flex-row items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Plan de evaluación
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm min-w-[260px]"
            value={data.selected_plan_id || ""}
            on:change={(e) => selectPlan(e.target.value)}
        >
            {#each data.plans as plan}
                <option value={plan.id}>
                    {plan.matter_name} · {plan.course_name} · {plan.section_name}{managesAll &&
                        plan.teacher_name
                        ? ` · ${plan.teacher_name}`
                        : ""}
                </option>
            {/each}
        </select>
    </div>
</div>

<!-- View Mode Toggle -->
<div class="flex gap-2 mb-4">
    <button
        class={`px-4 py-2 rounded-lg font-medium transition ${
            viewMode === "notas"
                ? "bg-yellow text-gray-900 "
                : "bg-white opacity-70 text-gray-700 hover:bg-gray-200"
        }`}
        on:click={() => (viewMode = "notas")}
    >
        Notas
    </button>

    <button
        class={`px-4 py-2 rounded-lg font-medium transition ${
            viewMode === "asistencia"
                ? "bg-yellow text-gray-900 "
                : "bg-white opacity-70 text-gray-700 hover:bg-gray-200"
        }`}
        on:click={() => {
            stopVoiceDictation();
            viewMode = "asistencia";
        }}
    >
        Asistencia
    </button>
</div>
{#if data.plans?.length}
    {#if managesAll}
        <div
            class="flex items-start gap-2 mb-4 px-4 py-3 rounded-xl border border-orange/30 bg-orange/10 text-xs text-color1"
        >
            <iconify-icon
                icon="mdi:information-outline"
                class="text-orange shrink-0 mt-px"
                width="16"
                height="16"
            ></iconify-icon>
            <p>
                Estás calificando en nombre del colegio. Los planes pertenecen a
                los profesores; las notas que registres quedarán marcadas con tu
                nombre. {#if planTeacherName}Plan actual:
                    <b>{planTeacherName}</b>.{/if}
            </p>
        </div>
    {/if}
{/if}

{#if !data.plans?.length}
    <div
        class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
    >
        Aún no tienes planes de evaluación. Crea un plan en "Planes de
        Evaluación" para poder calificar estudiantes.
    </div>
{/if}

{#if data.matrix && viewMode === "notas"}
    {#if data.matrix.students.length === 0}
        <div
            class="bg-white border border-grayBlue/40 rounded-2xl p-12 text-center text-gray-400 shadow-sm max-w-2xl mx-auto my-8"
        >
            <div
                class="w-12 h-12 rounded-2xl bg-color2/10 text-color2 flex items-center justify-center mx-auto mb-3"
            >
                <iconify-icon
                    icon="mdi:account-group-outline"
                    width="28"
                    height="28"
                ></iconify-icon>
            </div>
            <p class="font-bold text-color1 text-base">
                No hay estudiantes inscritos
            </p>
            <p class="text-xs text-gray-500 mt-1">
                {data.matrix.plan.course_name} · Sección {data.matrix.plan
                    .section_name}
            </p>
        </div>
    {:else}
        <!-- CONTENEDOR PRINCIPAL ELEVADO -->
        <div
            class="bg-white rounded-2xl border border-grayBlue/40 shadow-sm overflow-hidden mb-16"
        >
            <!-- HEADER SUPERIOR LIMPIO: FEEDBACK DE ESTADO / DICTADO ACTIVO -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-grayBlue/30 bg-slate-50/70 px-5 py-3 min-h-[52px]"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="text-xs font-bold text-color1 uppercase tracking-wider"
                        >Matriz de Calificaciones</span
                    >
                    <span class="text-gray-300">•</span>
                    <span class="text-xs text-gray-500 font-medium">
                        Total alumnos: <b class="text-color1"
                            >{sortedStudents.length}</b
                        >
                    </span>
                </div>

                <!-- Feedback contextual de dictado por voz -->
                {#if voiceListening && selectedVoiceItemId}
                    {@const activeItem = data.matrix.items.find(
                        (item) => String(item.id) === selectedVoiceItemId,
                    )}
                    <div
                        class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-red/10 border border-red/30 text-red text-xs font-semibold animate-pulse shadow-xs"
                    >
                        <span class="relative flex h-2.5 w-2.5">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red"
                            ></span>
                        </span>
                        <span
                            >Dictando en: <strong
                                class="underline decoration-red/40"
                                >{activeItem?.name ||
                                    "Tema seleccionado"}</strong
                            ></span
                        >
                        <span class="text-gray-400">•</span>
                        <span
                            class="text-[11px] font-normal text-gray-600 truncate max-w-[280px] sm:max-w-md"
                            >{voiceStatus}</span
                        >
                    </div>
                {:else}
                    <div
                        class="flex items-center gap-2 text-xs text-gray-400 font-medium"
                    >
                        <iconify-icon
                            icon="mdi:waveform"
                            class="text-color3 text-base"
                        ></iconify-icon>
                        <span
                            >{voiceStatus ||
                                "Selecciona 'Dictar notas' en cualquier tema para comenzar"}</span
                        >
                    </div>
                {/if}
            </div>

            <!-- ✅ Banner temporal de progreso -->
            {#if voiceProgressVisible && voiceListening}
                <div
                    class={`fixed bottom-20 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-xl border px-4 py-2 shadow-lg backdrop-blur-md transition-all duration-300 md:left-auto md:right-8 md:bottom-44 md:translate-x-0
            ${
                voiceProgressType === "completo"
                    ? "bg-emerald-50/95 border-emerald-500/40 ring-2 ring-emerald-500/20"
                    : voiceProgressType === "casi"
                      ? "bg-blue-50/95 border-blue/40 ring-2 ring-blue/20"
                      : "bg-white/95 border-grayBlue/40 ring-2 ring-grayBlue/10"
            }
            animate-in fade-in slide-in-from-bottom-2 duration-200
        `}
                    aria-live="polite"
                >
                    {#if voiceProgressType === "completo"}
                        <iconify-icon
                            icon="mdi:party-popper"
                            class="text-emerald-500 text-lg"
                        ></iconify-icon>
                    {:else if voiceProgressType === "casi"}
                        <iconify-icon
                            icon="mdi:flag-checkered"
                            class="text-blue text-lg"
                        ></iconify-icon>
                    {:else}
                        <iconify-icon
                            icon="mdi:progress-check"
                            class="text-gray-500 text-lg"
                        ></iconify-icon>
                    {/if}

                    <span
                        class={`text-xs font-bold ${
                            voiceProgressType === "completo"
                                ? "text-emerald-600"
                                : voiceProgressType === "casi"
                                  ? "text-blue"
                                  : "text-gray-700"
                        }`}
                    >
                        {voiceProgressMessage}
                    </span>
                </div>
            {/if}

            {#if voiceListening}
                <div
                    class={`fixed bottom-2 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-2xl border bg-white/95 px-5 py-3 shadow-2xl backdrop-blur-md ring-4 transition-all duration-300 md:left-auto md:right-8 md:bottom-24 md:translate-x-0
            ${voiceFlashType === "nota" ? "border-emerald-500/50 ring-emerald-500/30 scale-105 shadow-emerald-500/30 animate-[flash-nota_0.2s_ease-out]" : ""}
            ${voiceFlashType === "estudiante" ? "border-blue/50 ring-blue/30 scale-105 shadow-blue/30 animate-[flash-estudiante_0.2s_ease-out]" : ""}
            ${voiceFlashType === "error" ? "border-red/60 ring-red/30 scale-105 shadow-red/30 animate-[flash-error_0.2s_ease-out]" : ""}
            ${!voiceFlashType ? "border-red/30 ring-red/10" : ""}
        `}
                    aria-live="polite"
                >
                    <!-- Indicador pulsante (cambia según el tipo) -->
                    <span class="relative flex h-3.5 w-3.5 shrink-0">
                        <span
                            class={`absolute inline-flex h-full w-full rounded-full opacity-75 ${
                                voiceFlashType === "nota"
                                    ? "bg-emerald-700 animate-ping"
                                    : voiceFlashType === "estudiante"
                                      ? "bg-blue animate-ping"
                                      : "bg-red animate-ping"
                            }`}
                        ></span>
                        <span
                            class={`relative inline-flex h-3.5 w-3.5 rounded-full ${
                                voiceFlashType === "nota"
                                    ? "bg-emerald-600"
                                    : voiceFlashType === "estudiante"
                                      ? "bg-blue"
                                      : "bg-red"
                            }`}
                        ></span>
                    </span>

                    <div class="max-w-[260px] h-fit sm:max-w-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class={`text-[10px] font-bold uppercase tracking-wider transition-colors duration-200 ${
                                    voiceFlashType === "nota"
                                        ? "text-emerald-600"
                                        : voiceFlashType === "estudiante"
                                          ? "text-blue"
                                          : "text-red"
                                }`}
                            >
                                {voiceFlashType === "nota"
                                    ? "✓ Nota guardada"
                                    : voiceFlashType === "estudiante"
                                      ? "Estudiante detectado"
                                      : "Micrófono encendido"}
                            </span>
                            {#if voiceFlashType === "nota"}
                                <iconify-icon
                                    icon="mdi:check-circle"
                                    class="text-emerald-600 text-sm animate-in zoom-in duration-75"
                                ></iconify-icon>
                            {/if}
                            {#if voiceFlashType === "estudiante"}
                                <iconify-icon
                                    icon="mdi:account-check"
                                    class="text-blue text-sm animate-in zoom-in duration-100"
                                ></iconify-icon>
                            {/if}
                            {#if voiceFlashType === "error"}
                                <iconify-icon
                                    icon="mdi:alert-circle"
                                    class="text-red text-sm animate-in zoom-in duration-100"
                                ></iconify-icon>
                            {/if}
                        </div>
                        <p
                            class={`text-xs font-semibold truncate transition-colors duration-100 ${
                                voiceInterimText
                                    ? "text-color2"
                                    : voiceFlashType === "nota"
                                      ? "text-emerald-600"
                                      : voiceFlashType === "estudiante"
                                        ? "text-blue"
                                        : voiceFlashType === "error"
                                          ? "text-red"
                                          : "text-color1"
                            }`}
                        >
                            {#if voiceInterimText}
                                <span class="inline-flex items-center gap-1">
                                    <iconify-icon
                                        icon="lucide:mic"
                                        class="text-indigo-500 animate-pulse text-sm"
                                    ></iconify-icon>
                                    "{voiceInterimText}"
                                </span>
                            {:else}
                                {voiceStatus}
                            {/if}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="ml-1 p-1 rounded-lg text-gray-400 hover:text-red hover:bg-red/10 transition-colors"
                        title="Detener dictado"
                        on:click={toggleVoiceDictation}
                    >
                        <iconify-icon
                            icon="line-md:close"
                            width="18"
                            height="18"
                        ></iconify-icon>
                    </button>
                </div>
            {/if}

            <!-- TABLA DE CALIFICACIONES (DATA MATRIX) -->
            <div class="overflow-x-auto mb-">
                <table class="w-full text-xs md:text-sm border-collapse">
                    <thead
                        class="bg-gray-50/70 border-b border-grayBlue/30 text-gray-500"
                    >
                        <tr>
                            <!-- 1. Columna Estudiante (Sticky y compacta en móvil: max 125px-140px) -->
                            <th
                                class="px-2.5 py-2.5 md:px-5 md:py-3.5 text-left sticky left-0 z-20 bg-gray-50/95 backdrop-blur-sm w-[125px] min-w-[120px] max-w-[140px] md:w-auto md:min-w-[220px] md:max-w-none shadow-[1px_0_0_rgba(199,210,218,0.4)]"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 font-bold text-color1 hover:text-color2 transition-colors uppercase tracking-wider text-[10px] md:text-[11px]"
                                    on:click={() => toggleSort("student")}
                                >
                                    <span>Estudiante</span>
                                    <span class="text-color3 font-bold text-xs"
                                        >{getSortIndicator(
                                            "student",
                                            sortState,
                                        )}</span
                                    >
                                </button>
                            </th>
                            <!-- 2. Columnas de Temas / Evaluaciones -->
                            {#each data.matrix.items as item}
                                {@const meta = getUnitMetaForItem(item)}
                                {@const isThisItemListening =
                                    voiceListening &&
                                    String(selectedVoiceItemId) ===
                                        String(item.id)}
                                {@const isSelected =
                                    String(selectedVoiceItemId) ===
                                    String(item.id)}
                                <th
                                    class={`px-2 py-2.5 md:px-4 md:py-3 text-left w-[115px] min-w-[110px] max-w-[130px] md:w-auto md:min-w-[175px] md:max-w-none transition-colors align-top ${isThisItemListening ? "bg-red/5" : isSelected ? "bg-color4/10" : ""}`}
                                >
                                    <div class="flex flex-col gap-1.5 md:gap-2">
                                        <!-- ÁREA DEL TÍTULO (CON TOOLTIP AISLADO) -->
                                        <div class="group relative">
                                            <button
                                                type="button"
                                                class="flex w-full items-start justify-center text-center"
                                                on:click={() =>
                                                    toggleSort(
                                                        `item_${item.id}`,
                                                    )}
                                            >
                                                <div class="min-w-0 pr-1">
                                                    <span
                                                        class="font-bold text-color1 text-[11px] md:text-xs block truncate"
                                                        title={item.name}
                                                    >
                                                        {item.name}
                                                    </span>
                                                    <span
                                                        class="inline-block mt-0.5 text-[10px] md:text-[11px] font-semibold text-color3"
                                                    >
                                                        {item.percentage}%
                                                    </span>
                                                </div>
                                                <span
                                                    class="text-color3 text-xs mt-0.5 shrink-0"
                                                >
                                                    {getSortIndicator(
                                                        `item_${item.id}`,
                                                        sortState,
                                                    )}
                                                </span>
                                            </button>
                                            <!-- Tooltip (oculto en móvil con hidden md:block) -->
                                            <div
                                                class="hidden md:block pointer-events-none absolute left-1/2 top-full z-30 w-64 -translate-x-1/2 mt-1 rounded-xl border border-grayBlue/50 bg-white p-3 text-left text-xs text-gray-700 opacity-0 shadow-xl transition-all duration-150 group-hover:opacity-100 group-hover:translate-y-1"
                                            >
                                                <div
                                                    class="font-bold text-color1 flex items-center gap-1.5"
                                                >
                                                    <span
                                                        class="w-2 h-2 rounded-full bg-color2"
                                                    ></span>
                                                    Unidad {meta.unit_number}: "{meta.unit_name}"
                                                </div>
                                                <div
                                                    class="mt-2 space-y-1 text-[11px] text-gray-600"
                                                >
                                                    <div>
                                                        <span
                                                            class="font-semibold text-gray-500"
                                                            >Tipo:</span
                                                        >
                                                        {meta.assessment_type}
                                                    </div>
                                                    <div>
                                                        <span
                                                            class="font-semibold text-gray-500"
                                                            >Fecha:</span
                                                        >
                                                        {formatTooltipDate(
                                                            meta.scheduled_date,
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- BOTÓN CONTEXTUAL DE DICTADO -->
                                        <div
                                            class="flex items-center gap-1 justify-center"
                                        >
                                            {#if (corregidoInfo.counts[item.id] || 0) > 0}
                                                <span
                                                    class={`text-[9px] md:text-[10px] font-bold px-1.5 py-0.5 rounded-full flex items-center gap-0.5 border ${corregidoInfo.isAllCorrected(item.id) ? "bg-green/10 text-emerald-500 border-green/20" : "bg-gray-100 text-gray-600 border-grayBlue/40"}`}
                                                >
                                                    {#if corregidoInfo.isAllCorrected(item.id)}
                                                        <iconify-icon
                                                            icon="mdi:check-circle"
                                                            class="text-emerald-500 text-xs"
                                                        ></iconify-icon>
                                                    {/if}
                                                    {corregidoInfo.counts[
                                                        item.id
                                                    ]}/{corregidoInfo.total}
                                                </span>
                                            {/if}
                                            <button
                                                type="button"
                                                class={`flex items-center rounded-lg md:rounded-xl justify-center gap-1 border shadow-2xs transition-all duration-200 ${isThisItemListening ? "bg-red text-white border-red ring-2 ring-red/20 animate-pulse" : isSelected ? "bg-color1 text-white border-color1 hover:bg-color2" : "bg-white text-gray-500 border-grayBlue/50 hover:border-color2 hover:text-color2 hover:bg-slate-50"} ${(corregidoInfo.counts[item.id] || 0) > 0 ? "w-6 h-6 md:w-7 md:h-7 px-0" : "w-7 h-7 md:w-auto md:py-1 md:px-2.5 px-0 text-[10px] md:text-[11px] font-bold"}`}
                                                title="Dictar notas"
                                                on:click={() => {
                                                    if (isThisItemListening) {
                                                        toggleVoiceDictation();
                                                    } else {
                                                        selectedVoiceItemId =
                                                            String(item.id);
                                                        pendingVoiceStudentId =
                                                            null;
                                                        voiceStatus = `Escuchando para «${item.name}». Diga el nombre y la nota...`;
                                                        if (!voiceListening) {
                                                            toggleVoiceDictation();
                                                        }
                                                    }
                                                }}
                                            >
                                                <iconify-icon
                                                    icon={isThisItemListening
                                                        ? "mdi:microphone-off"
                                                        : isSelected
                                                          ? "mdi:microphone"
                                                          : "mdi:microphone-outline"}
                                                    class="text-xs md:text-sm"
                                                ></iconify-icon>
                                                {#if (corregidoInfo.counts[item.id] || 0) === 0}
                                                    <span
                                                        class="hidden md:inline"
                                                        >Dictar notas</span
                                                    >
                                                {/if}
                                            </button>
                                        </div>
                                    </div>
                                </th>
                            {/each}
                            <!-- 3. Columna de Rasgos Personales (si aplica) -->
                            {#if planRasgosMax > 0}
                                <th
                                    class="px-2 py-2.5 md:px-4 md:py-3 text-center bg-gray-50/80 w-[70px] min-w-[65px] md:w-auto md:min-w-[110px]"
                                >
                                    <span
                                        class="font-bold text-color1 uppercase tracking-wider text-[10px] md:text-[11px] block"
                                    >
                                        Rasgos
                                    </span>
                                    <span
                                        class="text-[10px] md:text-[11px] font-semibold text-color3"
                                        >({planRasgosMax})</span
                                    >
                                </th>
                            {/if}
                            <!-- 4. Columna Definitiva -->
                            <th
                                class="px-2.5 py-2.5 md:px-5 md:py-3.5 text-left bg-gray-50/80 w-[110px] min-w-[95px] md:w-auto md:min-w-[150px]"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 font-bold text-color1 hover:text-color2 transition-colors uppercase tracking-wider text-[10px] md:text-[11px]"
                                    on:click={() => toggleSort("definitive")}
                                >
                                    <span class="truncate"
                                        >Def. ({data.matrix.plan
                                            .lapse_label})</span
                                    >
                                    <span class="text-color3 font-bold text-xs"
                                        >{getSortIndicator(
                                            "definitive",
                                            sortState,
                                        )}</span
                                    >
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <!-- CUERPO DE ESTUDIANTES Y CALIFICACIONES -->
                    <tbody class="divide-y divide-grayBlue/20">
                        {#each sortedStudents as student}
                            {@const definitive =
                                definitiveByStudent[student.id]}
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- Celda Estudiante (Sticky fija a 125px-140px en móvil con truncado suave) -->
                                <td
                                    class={`px-2.5 py-2 md:px-5 md:py-3 sticky left-0 z-10 w-[125px] min-w-[120px] max-w-[140px] md:w-auto md:min-w-[220px] md:max-w-none transition-colors shadow-[1px_0_0_rgba(199,210,218,0.4)] ${pendingVoiceStudentId === student.id ? "bg-color4/10" : "bg-white group-hover:bg-slate-50"}`}
                                >
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="font-bold text-color1 text-xs md:text-sm truncate capitalize leading-tight"
                                                title="{student.last_name}, {student.name}"
                                            >
                                                {student.last_name}, {student.name}
                                            </p>
                                            <p
                                                class="text-[10px] md:text-[11px] font-mono text-gray-400 mt-0.5 leading-none truncate"
                                            >
                                                {student.ci}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            on:click={() =>
                                                openObservations(student)}
                                            class="relative flex-none w-6 h-6 md:w-7 md:h-7 rounded-md flex items-center justify-center {obsTotal(
                                                student,
                                            ) > 0
                                                ? "text-color2"
                                                : "text-gray-300"} hover:text-color2 hover:bg-blue/20 transition-colors"
                                            title={obsLabel(student)}
                                            aria-label={obsLabel(student)}
                                        >
                                            <iconify-icon
                                                icon="mdi:comment-text-outline"
                                                width="16"
                                                height="16"
                                            ></iconify-icon>

                                            <!-- Contador sobrepuesto: no ocupa ancho,
                                                 así el nombre sigue truncando en la
                                                 celda angosta de móvil. -->
                                            {#if obsTotal(student) > 0}
                                                <span
                                                    aria-hidden="true"
                                                    class="absolute -top-1 -right-1 min-w-[15px] h-[15px] px-[3px] inline-flex items-center justify-center rounded-full bg-color2 text-white text-[9px] font-bold leading-none border border-white"
                                                >
                                                    {obsTotal(student) > 99
                                                        ? "99+"
                                                        : obsTotal(student)}
                                                </span>
                                            {/if}
                                        </button>
                                    </div>
                                </td>

                                <!-- Celdas de Calificación por Tema -->
                                {#each data.matrix.items as item}
                                    {@const gradeKey = `${student.id}_${item.id}`}
                                    {@const isItemActive =
                                        String(selectedVoiceItemId) ===
                                        String(item.id)}
                                    {@const grader =
                                        student.graders?.[item.id] || null}
                                    <td
                                        class={`px-4 py-3 align-middle transition-colors ${isItemActive && voiceListening ? "bg-color4/10" : ""}`}
                                    >
                                        <div class="flex items-center gap-2">
                                            {#if data.matrix.plan.literary_grading_enabled}
                                                <select
                                                    class="w-12 h-9 rounded-xl border border-grayBlue/60 bg-white px-1 text-center font-bold text-xs text-color1 focus:border-color2 focus:ring-2 focus:ring-color2/20 focus:outline-none transition-all shadow-2xs"
                                                    aria-label={`Nota literaria de ${student.name} ${student.last_name} en ${item.name}`}
                                                    value={letterForScore(
                                                        editable[gradeKey],
                                                    )}
                                                    on:change={(event) =>
                                                        updateGrade(
                                                            gradeKey,
                                                            scoreForLetter(
                                                                event
                                                                    .currentTarget
                                                                    .value,
                                                            ),
                                                        )}
                                                >
                                                    <option value="">—</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                </select>
                                            {/if}

                                            <!-- Input Numérico Pulido -->
                                            <div class="relative mx-auto">
                                                <input
                                                    type="text"
                                                    data-grade-input={gradeKey}
                                                    inputmode="decimal"
                                                    pattern="[0-9.,]*"
                                                    title={grader
                                                        ? `Registrado por: ${grader}`
                                                        : undefined}
                                                    class={`w-16 h-9 mx-auto rounded-xl border text-center focus:border-color2 font-bold text-sm text-color1 transition-all shadow-2xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-color2/20`}
                                                    on:focus={(e) =>
                                                        e.currentTarget.select()}
                                                    on:keydown={(e) => {
                                                        if (
                                                            [
                                                                "ArrowUp",
                                                                "ArrowDown",
                                                                "PageUp",
                                                                "PageDown",
                                                                "Home",
                                                                "End",
                                                            ].includes(e.key)
                                                        ) {
                                                            e.stopPropagation();
                                                        }
                                                    }}
                                                    value={formatGrade(
                                                        editable[gradeKey],
                                                    )}
                                                    placeholder="—"
                                                    on:input={(event) =>
                                                        updateGrade(
                                                            gradeKey,
                                                            event.currentTarget
                                                                .value,
                                                        )}
                                                />
                                            </div>
                                        </div>
                                    </td>
                                {/each}

                                <!-- Celda de Rasgos -->
                                {#if planRasgosMax > 0}
                                    <td
                                        class="px-4 py-3 text-center bg-gray-50/40"
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            max={planRasgosMax}
                                            step="1"
                                            class="w-14 h-9 rounded-xl border border-grayBlue/60 bg-white text-center font-bold text-sm text-color1 transition-all shadow-2xs focus:border-color2 focus:ring-2 focus:ring-color2/20 focus:outline-none"
                                            inputmode="numeric"
                                            title={`Puntos de rasgos (0-${planRasgosMax})`}
                                            value={rasgosEditable[student.id]}
                                            placeholder="0"
                                            on:input={(event) =>
                                                updateRasgos(
                                                    student.id,
                                                    event.currentTarget.value,
                                                )}
                                        />
                                    </td>
                                {/if}

                                <!-- Celda Definitiva y Estado -->
                                <td class="px-5 py-3 bg-gray-50/40">
                                    {#if definitive !== null}
                                        {@const medal =
                                            getDefinitiveMedal(student)}
                                        <div class="flex items-center gap-2">
                                            {#if medal}
                                                <span
                                                    class="text-base leading-none shrink-0"
                                                    title={medal === "gold"
                                                        ? "1er Lugar"
                                                        : medal === "silver"
                                                          ? "2do Lugar"
                                                          : "3er Lugar"}
                                                >
                                                    <iconify-icon
                                                        icon={getMedalIcon(
                                                            medal,
                                                        ).icon}
                                                        class={getMedalIcon(
                                                            medal,
                                                        ).class}
                                                    ></iconify-icon>
                                                </span>
                                            {/if}
                                            {#if data.matrix.plan.literary_grading_enabled}
                                                {@const definitiveLetter =
                                                    letterForScore(definitive)}
                                                {#if definitiveLetter}
                                                    <span
                                                        class="rounded-lg bg-color4/20 border border-color4/40 px-2 py-0.5 text-xs font-bold text-color2"
                                                    >
                                                        {definitiveLetter}
                                                    </span>
                                                {/if}
                                            {/if}
                                            <!-- Píldora de Calificación Definitiva -->
                                            <span
                                                class={`inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold border ${
                                                    definitive >= 10
                                                        ? "bg-green/20 text-color1 border-green/30"
                                                        : "bg-red/10 text-red border-red/20"
                                                }`}
                                            >
                                                <span>{definitive}</span>
                                                {#if definitive < 10}
                                                    <span
                                                        class="text-[10px] font-black uppercase tracking-tight text-red"
                                                        >Aplazado</span
                                                    >
                                                {/if}
                                            </span>
                                        </div>
                                    {:else}
                                        <span
                                            class="inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1 rounded-full bg-yellow/20 text-gray-800 border border-yellow/40"
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full bg-yellow animate-pulse"
                                            ></span>
                                            En curso
                                        </span>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="mt-4 fixed bottom-8 right-10 gap-3 flex justify-end max-w-[600px] ml-auto items-center z-40"
        >
            {#if $form.isDirty}
                <button
                    on:click={handleSave}
                    class="max-w-[300px] animated-button flex items-center gap-10 hover:shadow-lg"
                    disabled={$form.processing}
                >
                    {#if $form.processing}
                        <span class="text">Guardando...</span>
                    {:else}
                        <span class="text">Guardar cambios</span>
                        <span class="circle"></span>
                    {/if}
                </button>
            {/if}

            {#if !$form.isDirty && canPublish}
                <button
                    type="button"
                    on:click={handlePublish}
                    disabled={$form.processing}
                    class="bg-color4 flex items-center gap-2 shadow-md hover:bg-color4/80 hover:shadow-lg rounded-full text-dark min-w-fit font-bold py-3 px-4 mt-5"
                >
                    <!-- share icon -->
                    <iconify-icon icon="entypo:publish" class="text-lg" />
                    Publicar notas
                </button>
            {/if}
        </div>
    {/if}
{:else if viewMode === "asistencia"}
    {#if !data.matrix}
        <div
            class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
        >
            Selecciona un plan para cargar la matriz de asistencia.
        </div>
    {:else}
        <AsistenciaMatrix
            planId={data.matrix.plan.id}
            students={sortedStudents}
        />
    {/if}
{/if}

<StudentObservationsDrawer
    bind:show={showObservations}
    plan={data.matrix?.plan ?? null}
    student={observationsStudent}
    on:changed={onObservationsChanged}
/>

<style>
    @keyframes flash-nota {
        0% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        }
        50% {
            box-shadow: 0 0 0 12px rgba(16, 185, 129, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }

    @keyframes flash-estudiante {
        0% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
        }
        50% {
            box-shadow: 0 0 0 12px rgba(59, 130, 246, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
        }
    }

    @keyframes flash-error {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        }
        50% {
            box-shadow: 0 0 0 12px rgba(239, 68, 68, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }
</style>
