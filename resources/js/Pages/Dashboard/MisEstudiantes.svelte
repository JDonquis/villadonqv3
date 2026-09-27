<script>
    import { onDestroy, onMount } from "svelte";
    import { useForm, router } from "@inertiajs/svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import axios from "axios";

    export let data = [];

    // View mode: 'notas' | 'asistencia'
    let viewMode = "notas";

    // Grades state
    let editable = {};
    let rasgosEditable = {};
    let selectedVoiceItemId = "";
    let voiceListening = false;
    let voiceStatus = "Selecciona un tema de la tabla y activa el micrófono.";
    let voiceRecognition = null;
    let pendingVoiceStudentId = null;

    // Attendance state
    let attendanceSessions = [];
    let attendanceData = {}; // sessionId -> { studentId: status }
    let newSessionDate = new Date().toISOString().split("T")[0];
    let attendanceSaving = false;
    let attendanceDirty = false;
    let lastLoadedPlanId = null;

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
            sortState.direction =
                sortState.direction === "asc" ? "desc" : "asc";
            return;
        }

        sortState.key = key;
        sortState.direction = "asc";
    }

    function getSortIndicator(key) {
        if (sortState.key !== key) return "↕";
        return sortState.direction === "asc" ? "▲" : "▼";
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
                const nameText = normalizeVoiceText(
                    `${student.name} ${student.last_name}`,
                );
                const nameWords = nameText.split(" ").filter(Boolean);

                let exactScore = 0;
                nameWords.forEach((nameWord) => {
                    if (words.includes(nameWord)) exactScore += 1;
                });

                // Boost exact score weight (B): each matched word counts double
                // This makes first+middle+last name combinations score significantly higher
                const weightedExactScore = exactScore * 2;

                let fuzzyScore = 0;
                if (nameText && transcriptText) {
                    const fullDistance = levenshteinDistance(
                        transcriptText,
                        nameText,
                    );
                    const fullRatio =
                        1 -
                        fullDistance /
                            Math.max(transcriptText.length, nameText.length, 1);
                    fuzzyScore = Math.max(fuzzyScore, fullRatio * 0.7);
                }

                words.forEach((word) => {
                    const bestWordMatch = nameWords.reduce(
                        (maxScore, nameWord) =>
                            Math.max(maxScore, tokenSimilarity(word, nameWord)),
                        0,
                    );
                    fuzzyScore = Math.max(fuzzyScore, bestWordMatch * 0.9);
                });

                const score = weightedExactScore + fuzzyScore;
                return { student, score };
            })
            .filter((entry) => entry.score > 0.25)
            .sort((a, b) => b.score - a.score);

        if (!matches.length) return { student: null, ambiguous: false };

        const bestScore = matches[0].score;
        // Tighten ambiguity threshold (C): from 0.95 to 0.99
        // Requires near-perfect score tie to trigger ambiguity
        const closest = matches.filter(
            (entry) => entry.score >= bestScore * 0.99,
        );

        return closest.length === 1
            ? { student: closest[0].student, ambiguous: false }
            : { student: null, ambiguous: true };
    }

    function parseVoiceScore(transcript) {
        const normalized = normalizeVoiceText(transcript);
        const digitMatch = normalized.match(
            /(?:^|\s)(\d{1,2}(?:[.,]\d+)?)(?:\s|$)/,
        );
        if (digitMatch) return Number(digitMatch[1].replace(",", "."));

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
        const scoreWord = words.find((word) =>
            Object.hasOwn(numberWords, word),
        );
        if (scoreWord === undefined) return null;

        let score = numberWords[scoreWord];
        const scoreIndex = words.indexOf(scoreWord);
        const separatorIndex = words.findIndex(
            (word, index) =>
                index > scoreIndex && ["coma", "punto"].includes(word),
        );
        if (separatorIndex !== -1) {
            const decimalWord = words[separatorIndex + 1];
            if (decimalWord && Object.hasOwn(numberWords, decimalWord)) {
                score += numberWords[decimalWord] / 10;
            }
        } else if (words.includes("medio")) {
            score += 0.5;
        }
        return score;
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
                voiceStatus = `No reconocí una nota en «${transcript}». Diga un número del 0 al 20.`;
                return;
            }
            if (score < 0 || score > 20) {
                voiceStatus =
                    "La nota debe estar entre 0 y 20. Diga el número nuevamente.";
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
                return;
            }
        }

        const { student, ambiguous } = findVoiceStudent(transcript);
        if (!student) {
            voiceStatus = ambiguous
                ? `Hay varios estudiantes que coinciden con «${transcript}». Diga también el apellido.`
                : `No encontré a «${transcript}». Diga su nombre y/o apellido.`;
            return;
        }

        pendingVoiceStudentId = student.id;
        focusVoiceGrade(student.id);
        voiceStatus = `${student.name} ${student.last_name}: input enfocado. Ahora diga la nota.`;
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
        return () => window.removeEventListener("keydown", handleKeyboardShortcut);
    });

    function resetVoiceSelection() {
        stopVoiceDictation();
        selectedVoiceItemId = "";
        pendingVoiceStudentId = null;
        voiceStatus = "Selecciona un tema de la tabla y activa el micrófono.";
    }

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
        recognition.interimResults = false;
        voiceRecognition = recognition;
        voiceListening = true;
        voiceStatus = "Escuchando: diga el nombre del estudiante.";

        recognition.onresult = (event) => {
            for (
                let index = event.resultIndex;
                index < event.results.length;
                index += 1
            ) {
                if (event.results[index].isFinal) {
                    processVoiceTranscript(event.results[index][0].transcript);
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

    // ===== ATTENDANCE FUNCTIONS =====

    async function loadAttendanceMatrix(planId) {
        try {
            const response = await axios.get(
                `/dashboard/mis-estudiantes/asistencia/${planId}`,
            );
            const matrix = response.data.data;
            attendanceSessions = matrix.sessions || [];
            attendanceData = matrix.attendance || {};
            attendanceDirty = false;
        } catch (error) {
            console.error("Error loading attendance:", error);
            displayAlert({
                type: "error",
                message: "Error al cargar asistencia",
            });
        }
    }

    async function createSession() {
        if (!data.matrix?.plan?.id || !newSessionDate) return;

        try {
            const response = await axios.post(
                "/dashboard/mis-estudiantes/asistencia/session",
                {
                    plan_id: data.matrix.plan.id,
                    date: newSessionDate,
                },
            );
            const session = response.data.data;
            attendanceSessions = [
                ...attendanceSessions,
                {
                    id: session.id,
                    date: session.date,
                    order: session.order,
                    day_of_week: formatDayOfWeek(session.date),
                },
            ].sort((a, b) => a.order - b.order || a.date.localeCompare(b.date));

            // Initialize attendance for new session
            attendanceData[session.id] = {};
            attendanceDirty = true;
            displayAlert({
                type: "success",
                message: "Sesión creada correctamente",
            });
        } catch (error) {
            console.error("Error creating session:", error);
            displayAlert({ type: "error", message: "Error al crear sesión" });
        }
    }

    async function deleteSession(sessionId) {
        if (
            !confirm(
                "¿Eliminar esta sesión de asistencia? Se borrarán todos los registros.",
            )
        )
            return;

        try {
            await axios.delete(
                `/dashboard/mis-estudiantes/asistencia/session/${sessionId}`,
            );
            attendanceSessions = attendanceSessions.filter(
                (s) => s.id !== sessionId,
            );
            delete attendanceData[sessionId];
            attendanceDirty = true;
            displayAlert({ type: "success", message: "Sesión eliminada" });
        } catch (error) {
            console.error("Error deleting session:", error);
            displayAlert({
                type: "error",
                message: "Error al eliminar sesión",
            });
        }
    }

    function toggleAttendance(sessionId, studentId) {
        const current = attendanceData[sessionId]?.[studentId] || "absent";
        const next =
            current === "absent"
                ? "present"
                : current === "present"
                  ? "excused"
                  : "absent";

        attendanceData[sessionId] = {
            ...attendanceData[sessionId],
            [studentId]: next,
        };
        attendanceData = { ...attendanceData };
        attendanceDirty = true;
    }

    function toggleAllInSession(sessionId) {
        const studentIds = (data.matrix?.students || []).map(
            (student) => student.id,
        );
        if (!studentIds.length) return;

        const sessionData = attendanceData[sessionId] || {};
        const allPresent =
            studentIds.length > 0 &&
            studentIds.every(
                (studentId) => sessionData[studentId] === "present",
            );
        const target = allPresent ? "absent" : "present";

        attendanceData[sessionId] = Object.fromEntries(
            studentIds.map((studentId) => [studentId, target]),
        );
        attendanceData = { ...attendanceData };
        attendanceDirty = true;
    }

    function formatDayOfWeek(dateStr) {
        const date = new Date(`${dateStr}T00:00:00`);
        return date
            .toLocaleDateString("es-VE", { weekday: "short" })
            .replace(/\./g, "")
            .trim();
    }

    function getAttendanceClass(status) {
        return status === "present"
            ? "bg-green-50 text-green-600"
            : status === "excused"
              ? "bg-orange-50 text-orange-600"
              : "bg-gray-50 text-gray-300";
    }

    function renderAttendanceIcon(status) {
        if (status === "present")
            return '<iconify-icon icon="mdi:check-bold" class="text-2xl"></iconify-icon>';
        if (status === "excused") return "J";
        return "—";
    }

    function isAllPresent(sessionId) {
        const studentIds = (data.matrix?.students || []).map(
            (student) => student.id,
        );
        if (!studentIds.length) return false;

        const sessionData = attendanceData[sessionId] || {};

        return studentIds.every(
            (studentId) => sessionData[studentId] === "present",
        );
    }

    function formatDate(dateStr) {
        if (!dateStr) return "—";
        const date = new Date(`${dateStr}T00:00:00`);
        return date.toLocaleDateString("es-VE", {
            day: "2-digit",
            month: "2-digit",
        });
    }

    async function saveAttendance() {
        if (!data.matrix?.plan?.id) return;

        attendanceSaving = true;

        // Build records array
        const records = [];
        attendanceSessions.forEach((session) => {
            Object.entries(attendanceData[session.id] || {}).forEach(
                ([studentId, status]) => {
                    records.push({
                        session_id: session.id,
                        student_id: parseInt(studentId),
                        status,
                    });
                },
            );
        });

        try {
            await router.post(
                "/dashboard/mis-estudiantes/asistencia/save",
                {
                    plan_id: data.matrix.plan.id,
                    records,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        attendanceDirty = false;
                        attendanceSaving = false;
                        displayAlert({
                            type: "success",
                            message: "Asistencia guardada correctamente",
                        });
                    },
                    onError: (errors) => {
                        attendanceSaving = false;
                        displayAlert({
                            type: "error",
                            message:
                                errors.message || "Error al guardar asistencia",
                        });
                    },
                },
            );
        } catch (error) {
            attendanceSaving = false;
            console.error("Error saving attendance:", error);
            displayAlert({
                type: "error",
                message: "Error al guardar asistencia",
            });
        }
    }

    // Watch for plan change to load attendance
    $: if (
        viewMode === "asistencia" &&
        data.matrix?.plan?.id &&
        data.matrix.plan.id !== lastLoadedPlanId
    ) {
        lastLoadedPlanId = data.matrix.plan.id;
        loadAttendanceMatrix(data.matrix.plan.id);
    }

    // Reset attendance state when switching away from asistencia or changing plan
    $: if (viewMode !== "asistencia") {
        attendanceSessions = [];
        attendanceData = {};
        attendanceDirty = false;
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
                    {plan.matter_name} · {plan.course_name} · {plan.section_name}
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
            if (data.matrix?.plan?.id && !attendanceSessions.length) {
                loadAttendanceMatrix(data.matrix.plan.id);
            }
        }}
    >
        Asistencia
    </button>
</div>
{#if !data.plans?.length}
    <div
        class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
    >
        Aún no tienes planes de evaluación. Crea un plan en "Planes de
        Evaluación" para poder calificar estudiantes.
    </div>
{/if}

{#if data.matrix && viewMode === 'notas'}
    {#if data.matrix.students.length === 0}
        <div class="bg-white border border-grayBlue/40 rounded-2xl p-12 text-center text-gray-400 shadow-sm max-w-2xl mx-auto my-8">
            <div class="w-12 h-12 rounded-2xl bg-color2/10 text-color2 flex items-center justify-center mx-auto mb-3">
                <iconify-icon icon="mdi:account-group-outline" width="28" height="28"></iconify-icon>
            </div>
            <p class="font-bold text-color1 text-base">No hay estudiantes inscritos</p>
            <p class="text-xs text-gray-500 mt-1">
                {data.matrix.plan.course_name} · Sección {data.matrix.plan.section_name}
            </p>
        </div>
    {:else}
        <!-- CONTENEDOR PRINCIPAL ELEVADO -->
        <div class="bg-white rounded-2xl border border-grayBlue/40 shadow-sm overflow-hidden mb-12">

            <!-- HEADER SUPERIOR LIMPIO: FEEDBACK DE ESTADO / DICTADO ACTIVO -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-grayBlue/30 bg-slate-50/70 px-5 py-3 min-h-[52px]">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-color1 uppercase tracking-wider">Matriz de Calificaciones</span>
                    <span class="text-gray-300">•</span>
                    <span class="text-xs text-gray-500 font-medium">
                        Total alumnos: <b class="text-color1">{sortedStudents.length}</b>
                    </span>
                </div>

                <!-- Feedback contextual de dictado por voz -->
                {#if voiceListening && selectedVoiceItemId}
                    {@const activeItem = data.matrix.items.find((item) => String(item.id) === selectedVoiceItemId)}
                    <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-red/10 border border-red/30 text-red text-xs font-semibold animate-pulse shadow-xs">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red"></span>
                        </span>
                        <span>Dictando en: <strong class="underline decoration-red/40">{activeItem?.name || "Tema seleccionado"}</strong></span>
                        <span class="text-gray-400">•</span>
                        <span class="text-[11px] font-normal text-gray-600 truncate max-w-[280px] sm:max-w-md">{voiceStatus}</span>
                    </div>
                {:else}
                    <div class="flex items-center gap-2 text-xs text-gray-400 font-medium">
                        <iconify-icon icon="mdi:waveform" class="text-color3 text-base"></iconify-icon>
                        <span>{voiceStatus || "Selecciona 'Dictar notas' en cualquier tema para comenzar"}</span>
                    </div>
                {/if}

            </div>

            <!-- Banner flotante sutil en pantalla solo cuando el micrófono está en escucha -->
            {#if voiceListening}
                <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-2xl border border-red/30 bg-white/95 px-5 py-3 shadow-2xl backdrop-blur-md ring-4 ring-red/10 animate-in fade-in zoom-in-95 duration-200 md:left-auto md:right-8 md:bottom-8 md:translate-x-0" aria-live="polite">
                    <span class="relative flex h-3.5 w-3.5 shrink-0">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red opacity-75"></span>
                        <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-red"></span>
                    </span>
                    <div class="max-w-[260px] sm:max-w-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-red">Micrófono encendido</span>
                        </div>
                        <p class="text-xs font-semibold text-color1 truncate">{voiceStatus}</p>
                    </div>
                    <button type="button" class="ml-1 p-1 rounded-lg text-gray-400 hover:text-red hover:bg-red/10 transition-colors" title="Detener dictado" on:click={toggleVoiceDictation}>
                        <iconify-icon icon="line-md:close" width="18" height="18"></iconify-icon>
                    </button>
                </div>
            {/if}

            <!-- TABLA DE CALIFICACIONES (DATA MATRIX) -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs md:text-sm border-collapse">
                    <thead class="bg-gray-50/70 border-b border-grayBlue/30 text-gray-500">
                        <tr>
                            <!-- 1. Columna Estudiante (Sticky) -->
                            <th class="px-5 py-3.5 text-left sticky left-0 z-20 bg-gray-50/95 backdrop-blur-sm min-w-[220px] shadow-[1px_0_0_rgba(199,210,218,0.4)]">
                                <button type="button" class="inline-flex items-center gap-1.5 font-bold text-color1 hover:text-color2 transition-colors uppercase tracking-wider text-[11px]" on:click={() => toggleSort("student")}>
                                    <span>Estudiante</span>
                                    <span class="text-color3 font-bold text-xs">{getSortIndicator("student")}</span>
                                </button>
                            </th>

                            <!-- 2. Columnas de Temas / Evaluaciones -->
                            {#each data.matrix.items as item}
                                {@const meta = getUnitMetaForItem(item)}
                                {@const isThisItemListening = voiceListening && String(selectedVoiceItemId) === String(item.id)}
                                {@const isSelected = String(selectedVoiceItemId) === String(item.id)}
                                <th class={`px-4 py-3 text-left min-w-[175px] transition-colors align-top ${isThisItemListening ? 'bg-red/5' : isSelected ? 'bg-color4/10' : ''}`}>
                                    <div class="flex flex-col gap-2">
                                        <!-- ÁREA DEL TÍTULO (AQUÍ ESTÁ EL GROUP PARA EL TOOLTIP AISLADO) -->
                                        <div class="group relative">
                                            <button type="button" class="flex w-full items-start justify-between gap-1 text-left" on:click={() => toggleSort(`item_${item.id}`)}>
                                                <div class="min-w-0 pr-1">
                                                    <span class="font-bold text-color1 text-xs block truncate" title={item.name}>
                                                        {item.name}
                                                    </span>
                                                    <span class="inline-block mt-0.5 text-[11px] font-semibold text-color3">
                                                        {item.percentage}% Ponderación
                                                    </span>
                                                </div>
                                                <span class="text-color3 text-xs mt-0.5 shrink-0">{getSortIndicator(`item_${item.id}`)}</span>
                                            </button>

                                            <!-- TOOLTIP AISLADO: SOLO APARECE AL HACER HOVER SOBRE EL TÍTULO -->
                                            <div class="pointer-events-none absolute left-1/2 top-full z-30 w-64 -translate-x-1/2 mt-1 rounded-xl border border-grayBlue/50 bg-white p-3 text-left text-xs text-gray-700 opacity-0 shadow-xl transition-all duration-150 group-hover:opacity-100 group-hover:translate-y-1">
                                                <div class="font-bold text-color1 flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-color2"></span>
                                                    Unidad {meta.unit_number}: "{meta.unit_name}"
                                                </div>
                                                <div class="mt-2 space-y-1 text-[11px] text-gray-600">
                                                    <div><span class="font-semibold text-gray-500">Tipo:</span> {meta.assessment_type}</div>
                                                    <div><span class="font-semibold text-gray-500">Fecha:</span> {formatTooltipDate(meta.scheduled_date)}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- BOTÓN CONTEXTUAL DE DICTADO (FUERA DEL GROUP: NUNCA ACTIVA EL TOOLTIP) -->
                                        <button type="button" class={`w-full py-1.5 px-2.5 rounded-xl text-[11px] font-bold tracking-tight transition-all duration-200 flex items-center justify-center gap-1.5 border shadow-2xs ${
                                            isThisItemListening
                                                ? 'bg-red text-white border-red shadow-red/25 ring-2 ring-red/20 animate-pulse'
                                                : isSelected
                                                    ? 'bg-color1 text-white border-color1 hover:bg-color2'
                                                    : 'bg-white text-gray-700 border-grayBlue/60 hover:border-color2 hover:text-color2 hover:bg-slate-50'
                                        }`} title="Atajo de teclado: Ctrl + M (o Cmd + M en Mac)" on:click={() => {
                                            if (isThisItemListening) {
                                                // Si ya está escuchando este tema, lo apaga
                                                toggleVoiceDictation();
                                            } else {
                                                // Selecciona el tema e inicia el micrófono en 1 solo paso
                                                selectedVoiceItemId = String(item.id);
                                                pendingVoiceStudentId = null;
                                                voiceStatus = `Escuchando para «${item.name}». Diga el nombre y la nota...`;
                                                if (!voiceListening) {
                                                    toggleVoiceDictation();
                                                }
                                            }
                                        }}>
                                            <iconify-icon icon={isThisItemListening ? "mdi:microphone-off" : isSelected ? "mdi:microphone" : "mdi:microphone-outline"} class="text-sm"></iconify-icon>
                                            <span>
                                                {isThisItemListening ? "Detener dictado" : isSelected ? "Dictar notas" : "Dictar notas"}
                                            </span>
                                        </button>
                                    </div>
                                </th>
                            {/each}

                            <!-- 3. Columna de Rasgos Personales (si aplica) -->
                            {#if planRasgosMax > 0}
                                <th class="px-4 py-3 text-center bg-gray-50/80 min-w-[110px]" title="Puntos de rasgos (conducta y puntualidad)">
                                    <span class="font-bold text-color1 uppercase tracking-wider text-[11px] block">
                                        Rasgos
                                    </span>
                                    <span class="text-[11px] font-semibold text-color3">({planRasgosMax} pts)</span>
                                </th>
                            {/if}

                            <!-- 4. Columna Definitiva -->
                            <th class="px-5 py-3.5 text-left bg-gray-50/80 min-w-[150px]">
                                <button type="button" class="inline-flex items-center gap-1.5 font-bold text-color1 hover:text-color2 transition-colors uppercase tracking-wider text-[11px]" on:click={() => toggleSort("definitive")}>
                                    <span>Definitiva ({data.matrix.plan.lapse_label})</span>
                                    <span class="text-color3 font-bold text-xs">{getSortIndicator("definitive")}</span>
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <!-- CUERPO DE ESTUDIANTES Y CALIFICACIONES -->
                    <tbody class="divide-y divide-grayBlue/20">
                        {#each sortedStudents as student}
                            {@const definitive = definitiveByStudent[student.id]}
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- Celda Estudiante (Sticky) -->
                                <td class="px-5 py-3 sticky left-0 z-10 bg-white group-hover:bg-slate-50 transition-colors shadow-[1px_0_0_rgba(199,210,218,0.4)]">
                                    <div class="flex items-center gap-3">
                                       
                                        <div class="min-w-0">
                                            <p class="font-bold text-color1 text-sm truncate capitalize">
                                                {student.last_name}, {student.name}
                                            </p>
                                            <p class="text-[11px] font-mono text-gray-400 mt-0">
                                                C.I. {student.ci}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Celdas de Calificación por Tema -->
                                {#each data.matrix.items as item}
                                    {@const gradeKey = `${student.id}_${item.id}`}
                                    {@const isItemActive = String(selectedVoiceItemId) === String(item.id)}
                                    <td class={`px-4 py-3 align-middle transition-colors ${isItemActive && voiceListening ? 'bg-color4/10' : ''}`}>
                                        <div class="flex items-center gap-2">
                                            {#if data.matrix.plan.literary_grading_enabled}
                                                <select
                                                    class="w-12 h-9 rounded-xl border border-grayBlue/60 bg-white px-1 text-center font-bold text-xs text-color1 focus:border-color2 focus:ring-2 focus:ring-color2/20 focus:outline-none transition-all shadow-2xs"
                                                    aria-label={`Nota literaria de ${student.name} ${student.last_name} en ${item.name}`}
                                                    value={letterForScore(editable[gradeKey])}
                                                    on:change={(event) =>
                                                        updateGrade(
                                                            gradeKey,
                                                            scoreForLetter(event.currentTarget.value),
                                                        )}
                                                >
                                                    <option value="">—</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                </select>
                                            {/if}

                                            <!-- Input Numérico Pulido -->
                                            <div class="relative">
                                                <input
                                                    type="number"
                                                    data-grade-input={gradeKey}
                                                    min="0"
                                                    max="20"
                                                    step="0.5"
                                                    class={`w-16 h-9 rounded-xl border text-center focus:border-color2 font-bold text-sm text-color1 transition-all shadow-2xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-color2/20`}
                                                    inputmode="decimal"
                                                    on:wheel|preventDefault
                                                    on:focus={(e) => e.currentTarget.select()}
                                                    on:keydown={(e) => {
                                                        if (["ArrowUp", "ArrowDown", "PageUp", "PageDown", "Home", "End"].includes(e.key)) {
                                                            e.stopPropagation();
                                                        }
                                                    }}
                                                    value={editable[gradeKey]}
                                                    placeholder="—"
                                                    on:input={(event) =>
                                                        updateGrade(
                                                            gradeKey,
                                                            event.currentTarget.value,
                                                        )}
                                                />
                                            </div>
                                        </div>
                                    </td>
                                {/each}

                                <!-- Celda de Rasgos -->
                                {#if planRasgosMax > 0}
                                    <td class="px-4 py-3 text-center bg-gray-50/40">
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
                                        {@const medal = getDefinitiveMedal(student)}
                                        <div class="flex items-center gap-2">
                                            {#if medal}
                                                <span class="text-base leading-none shrink-0" title={medal === "gold" ? "1er Lugar" : medal === "silver" ? "2do Lugar" : "3er Lugar"}>
                                                    <iconify-icon icon={getMedalIcon(medal).icon} class={getMedalIcon(medal).class}></iconify-icon>
                                                </span>
                                            {/if}
                                            {#if data.matrix.plan.literary_grading_enabled}
                                                {@const definitiveLetter = letterForScore(definitive)}
                                                {#if definitiveLetter}
                                                    <span class="rounded-lg bg-color4/20 border border-color4/40 px-2 py-0.5 text-xs font-bold text-color2">
                                                        {definitiveLetter}
                                                    </span>
                                                {/if}
                                            {/if}
                                            <!-- Píldora de Calificación Definitiva -->
                                            <span class={`inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold border ${
                                                definitive >= 10
                                                    ? 'bg-green/15 text-color1 border-green/30'
                                                    : 'bg-red/10 text-red border-red/20'
                                            }`}>
                                                <span>{definitive}</span>
                                                {#if definitive < 10}
                                                    <span class="text-[10px] font-black uppercase tracking-tight text-red">Aplazado</span>
                                                {/if}
                                            </span>
                                        </div>
                                    {:else}
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1 rounded-full bg-yellow/20 text-gray-800 border border-yellow/40">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow animate-pulse"></span>
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
                        <span class="text">Guardar notas</span>
                        <span class="circle"></span>
                    {/if}
                </button>
            {/if}

            {#if !$form.isDirty && canPublish}
                <button
                    type="button"
                    on:click={handlePublish}
                    disabled={$form.processing}
                    class="bg-color4 hover:bg-color4/80 hover:shadow-lg rounded-full text-dark min-w-fit font-bold py-3 px-4 mt-5"
                >
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
        <div
            class="bg-white border border-gray-200 rounded-lg shadow overflow-hidden"
        >
            <!-- Add Session Bar -->
            <div
                class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row md:items-center gap-4"
            >
                <div class="flex items-center gap-3">
                    <label class="text-sm font-semibold text-gray-700"
                        >Nueva sesión:</label
                    >
                    <input
                        type="date"
                        bind:value={newSessionDate}
                        max={new Date().toISOString().split("T")[0]}
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    <button
                        on:click={createSession}
                        class="bg-color1 text-white px-4 py-2 rounded-md hover:bg-color1/90 text-sm"
                        disabled={!newSessionDate}
                    >
                        Agregar sesión
                    </button>
                </div>
            </div>

            {#if attendanceSessions.length === 0}
                <div class="p-8 text-center text-gray-400">
                    No hay sesiones de asistencia. Agrega una sesión para
                    comenzar.
                </div>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-fit text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-3 py-3 text-left sticky left-0 bg-gray-50 z-10"
                                >
                                    <div class="font-semibold text-gray-800">
                                        Estudiante
                                    </div>
                                </th>
                                {#each attendanceSessions as session}
                                    <th
                                        class="px-2 py-3 text-center justify-center relative group"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-1"
                                        >
                                            <span
                                                class="font-semibold text-gray-800"
                                                >{formatDate(
                                                    session.date,
                                                )}</span
                                            >
                                            <span
                                                class="text-xs text-gray-500 capitalize"
                                                >{session.day_of_week}</span
                                            >
                                            <button
                                                class="absolute top-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 hover:bg-red-200 opacity-0 group-hover:opacity-100 transition-opacity"
                                                on:click={() =>
                                                    deleteSession(session.id)}
                                                title="Eliminar sesión"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                        <!-- Mark All Toggle -->
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 accent-green-600 cursor-pointer"
                                            checked={isAllPresent(session.id)}
                                            title="marcar /desmarcar todos"
                                            on:change={() =>
                                                toggleAllInSession(session.id)}
                                            aria-label={`Marcar todos de la sesión ${session.date}`}
                                        />
                                    </th>
                                {/each}
                            </tr>
                        </thead>
                        <tbody>
                            {#each sortedStudents as student}
                                <tr class="border-t border-gray-100">
                                    <td
                                        class="px-3 py-2 sticky left-0 bg-white z-10"
                                    >
                                        <p
                                            class="font-semibold text-gray-800 capitalize"
                                        >
                                            {student.last_name}, {student.name}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            C.I {student.ci}
                                        </p>
                                    </td>
                                    {#each attendanceSessions as session}
                                        <td
                                            class="px-2 w-[44px] aspect-square h-[44px] min-h-[44px] py-2 text-center"
                                        >
                                            <button
                                                class="w-[44px] hover:text-gray-400 hover:bg-gray-200 aspect-square h-[44px] min-h-[44px] flex items-center justify-center text-2xl transition-colors rounded {getAttendanceClass(
                                                    attendanceData[
                                                        session.id
                                                    ]?.[student.id] || 'absent',
                                                )}"
                                                on:click={() =>
                                                    toggleAttendance(
                                                        session.id,
                                                        student.id,
                                                    )}
                                            >
                                                {#if (attendanceData[session.id]?.[student.id] || "absent") === "present"}
                                                    <iconify-icon
                                                        icon="mdi:check-bold"
                                                        class="text-2xl"
                                                    ></iconify-icon>
                                                {:else if (attendanceData[session.id]?.[student.id] || "absent") === "excused"}
                                                    J
                                                {:else}
                                                    —
                                                {/if}
                                            </button>
                                        </td>
                                    {/each}
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>

        <!-- Save Button -->
        <div
            class="mt-4 fixed bottom-8 right-10 gap-3 flex justify-end max-w-[600px] ml-auto items-center"
        >
            {#if attendanceDirty}
                <button
                    on:click={saveAttendance}
                    class="animated-button flex items-center gap-3"
                    disabled={attendanceSaving}
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="arr-2"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                        ></path>
                    </svg>
                    {#if attendanceSaving}
                        <span class="text">Guardando...</span>
                    {:else}
                        <iconify-icon
                            icon="material-symbols:save"
                            class="text"
                            width="20"
                            height="20"
                        ></iconify-icon>
                        <span class="text">Guardar asistencia</span>
                    {/if}
                    <span class="circle"></span>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="arr-1"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                        ></path>
                    </svg>
                </button>
            {/if}
        </div>
    {/if}
{/if}
