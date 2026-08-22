<script>
    import Table from "../../components/Table.svelte";
    import Modal from "../../components/Modal.svelte";
    import Input from "../../components/Input.svelte";
    import axios from "axios";
    import debounce from "lodash/debounce";

    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import { useForm, router, page } from "@inertiajs/svelte";
    import { claim_svg_element } from "svelte/internal";
    import SelectableRow from "../../components/SelectableRow.svelte";
    import Search from "../../components/Search.svelte";

    export let data = []; 

    $: selectedCourseId = (data.filters?.course_id || "1").toString();

    $: sectionsOfThisYear =
        data.course_sections?.data?.[`course_${data.filters.course_id}`];

    $: lastSectionId = sectionsOfThisYear?.[sectionsOfThisYear?.length - 1].id;

    const generarCorreoAleatorio = () => {
        const caracteres = "abcdefghijklmnopqrstuvwxyz0123456789";
        let prefijo = "";

        // Generamos 8 caracteres aleatorios
        for (let i = 0; i < 8; i++) {
            prefijo += caracteres.charAt(
                Math.floor(Math.random() * caracteres.length),
            );
        }

        // Añadimos el timestamp actual para garantizar unicidad
        const timestamp = Date.now().toString(36);

        return `${prefijo}-${timestamp}@test.test`;
    };
    let form = useForm({
        student_name: "",
        student_last_name: "",
        student_date_birth: "",
        student_email: "",
        student_ci: "",
        student_document_type: "V",
        student_phone_number: "",
        course_id: 1,
        section_id: "",
        student_sex: "",
        student_previous_school: "",
        state: "",
        city: "",
        address: "",
        rep_name: "",
        rep_last_name: "",
        rep_ci: "",
        rep_document_type: "V",
        rep_phone_number: "",
        rep_phone_number2: "",
        rep_email: generarCorreoAleatorio(),
        rep_profession: "",
        rep_workplace: "",
        second_rep_name: "",
        second_rep_last_name: "",
        second_rep_ci: "",
        second_rep_document_type: "V",
        second_rep_phone_number: "",
        second_rep_phone_number2: "",
        second_rep_email: "",
        second_rep_profession: "",
        second_rep_workplace: "",
        rep_id: null,
        is_exempt: false,
        exemption_percentage: "",
        exemption_observations: "",
        apply_to_past_debts: false,
    });

    let formReinscribe = useForm({
        course_id: 1,
        section_id: "",
        student_id: "",
    });

    let submitStatus = "Crear";
    let editingStudentId = null;

    let showModal = false;
    let showModalReinscribe = false;
    let selectedRow = { status: false, data: null };

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, data: null };
        }
    });

    function handleSubmit(event) {
        event.preventDefault();
        if (submitStatus === "Crear") {
            $form.clearErrors();
            $form.post("/dashboard/matricula", {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Estudiante creado correctamente",
                    });
                    showModal = false;
                },
            });
        } else if (submitStatus === "Editar") {
            $form.clearErrors();
            $form.put(`/dashboard/matricula/${editingStudentId}`, {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Estudiante actualizado correctamente",
                    });
                    showModal = false;
                    submitStatus = "Crear";
                    editingStudentId = null;
                    selectedRow = { status: false, data: null };
                },
            });
        }
    }

    function handleDelete(id) {
        $form.delete(`/dashboard/matricula/${id}`, {
            onBefore: () =>
                confirm(`¿Está seguro de eliminar a este estudiante?`),
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Estudiante eliminado correctamente",
                });
                selectedRow = { status: false, data: null };
            },
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
        });
    }

    function fillFormToEdit() {
        showModal = true;

        console.log(selectedRow);
        const student = selectedRow.data;
        submitStatus = "Editar";
        editingStudentId = student.student_id;
        $form.student_name = student.student_name;
        $form.student_last_name = student.student_last_name;
        $form.student_date_birth = student.student_date_birth;
        $form.student_email = student.student_email;
        $form.student_ci = student.student_ci;
        $form.student_document_type = student.student_document_type;
        $form.student_phone_number = student.student_phone_number;
        $form.course_id = student.course_id;
        $form.section_id = student.section_id;
        $form.student_sex = student.student_sex;
        $form.student_previous_school = student.previous_school;
        $form.state = student.state;
        $form.city = student.city;
        $form.address = student.address;
        $form.rep_name = student.rep_name;
        $form.rep_last_name = student.rep_last_name;
        $form.rep_ci = student.rep_ci;
        $form.rep_document_type = student.rep_document_type;
        $form.rep_phone_number = student.rep_phone_number;
        $form.rep_phone_number2 = student.rep_phone_number2;
        $form.rep_email = student.rep_email;
        $form.rep_profession = student.rep_profession;
        $form.rep_workplace = student.rep_workplace;
        $form.rep_id = student.rep_id;
        $form.second_rep_name = student.second_rep_name;
        $form.second_rep_last_name = student.second_rep_last_name;
        $form.second_rep_ci = student.second_rep_ci;
        $form.second_rep_document_type = student.second_rep_document_type;
        $form.second_rep_phone_number = student.second_rep_phone_number;
        $form.second_rep_phone_number2 = student.second_rep_phone_number2;
        $form.second_rep_email = student.second_rep_email;
        $form.second_rep_profession = student.second_rep_profession;
        $form.second_rep_workplace = student.second_rep_workplace;
        $form.is_exempt = student.is_exempt ?? false;
        $form.exemption_percentage = student.exemption_percentage ?? "";
        $form.exemption_observations = student.exemption_observations ?? "";
        $form.apply_to_past_debts = student.apply_to_past_debts ?? false;
    }

    function handleInscribeClick() {
        showModalReinscribe = true;
        const student = selectedRow.data;
        $formReinscribe.course_id = student.course_id - 1 || 1;
        $formReinscribe.section_id = student.section_id;
        $formReinscribe.student_id = student.student_id;
        console.log(showModalReinscribe);
    }

    function handleSubmitReinscribe(e) {
        e.preventDefault();
        $formReinscribe.clearErrors();
        $formReinscribe.post("/dashboard/matricula/reinscribir", {
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
            onSuccess: () => {
                $formReinscribe.reset();
                displayAlert({
                    type: "success",
                    message: "Estudiante reinscrito correctamente",
                });
                showModal = false;
            },
        });
    }

    function createSection() {
        router.post(
            "/dashboard/secciones",
            { course_id: data.filters.course_id, section_id: lastSectionId },
            {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: (mensaje) => {
                    displayAlert({
                        type: "success",
                        message: "Ok todo salió bien",
                    });
                },
            },
        );
    }

    function deleteSection() {
        router.delete(
            `/dashboard/secciones/${data.filters.course_id}/${lastSectionId}`,
            {
                onBefore: () =>
                    confirm(`¿Está seguro de eliminar esta sección?`),
            },
        );
    }
    function changeYear(course_id) {
        console.log("Cambiando curso a:", course_id);
        const params = {
            ...data.filters,
            course_id: course_id,
            section_id: 1, // Reset section to 1 when year changes
        };
        router.get(window.location.pathname, params, {
            preserveState: false, // Ensure we get fresh data
            replace: true,
        });
    }

    const search_rep1 = debounce(async (ci) => {
        try {
            const response = await axios.get(
                `/dashboard/matricula/search-representative/${ci}`,
            );
            const rep = response.data;
            if (!rep.rep_id) {
                return;
            }
            $form.rep_name = rep.rep_name;
            $form.rep_last_name = rep.rep_last_name;
            $form.rep_phone_number = rep.rep_phone_number;
            $form.rep_email = rep.rep_email;
            $form.rep_profession = rep.rep_profession;
            $form.rep_workplace = rep.rep_workplace;
            $form.rep_id = rep.rep_id;

            console.log(rep);
        } catch (error) {}
    }, 300);

    function search_second(ci) {
        router.get(`/dashboard/matricula/search-second_representative/`, {
            ci,
        });
    }
</script>

<svelte:head>
    <title>Matricula</title>
</svelte:head>

<Alert />

<Modal bind:showModal={showModalReinscribe} classes={"w-96"}>
    <form class="px-2" id="r-form" on:submit={handleSubmitReinscribe}>
        {#if $formReinscribe.course_id == 1}
            <p class="text-center p-5">
                Como este estudiante está en 5to año, al dar click en el botón
                de reinscribir quedará como graduado
            </p>
        {:else}
            <Input
                type="select"
                required={true}
                label={"Año escolar"}
                bind:value={$formReinscribe.course_id}
                error={$formReinscribe.errors?.course_id}
                disabled={submitStatus == "Editar"}
            >
                {#each data.courses as course}
                    <option value={course.id}>{course.name}</option>
                {/each}
            </Input>
            <Input
                type="select"
                required={true}
                label={"Sección"}
                bind:value={$formReinscribe.section_id}
                error={$formReinscribe.errors?.section_id}
            >
                {#each data.course_sections?.data?.[`course_${$formReinscribe.course_id}`] as section}
                    <option value={section.id}>{section.name}</option>
                {/each}
            </Input>
        {/if}
    </form>
    <button
        form="r-form"
        slot="btn_footer"
        type="submit"
        class="animated-button w-full flex items-center justify-center gap-3"
        disabled={$formReinscribe.processing}
    >
        {#if $formReinscribe.processing}
            Cargando...
        {:else}
            <iconify-icon
                icon="material-symbols:save-sharp"
                width="24"
                height="24"
            />
            <span>Reinscribir</span>
        {/if}
    </button>
</Modal>

<Modal bind:showModal classes={"w-fit"}>
    <form
        id="a-form"
        on:submit={handleSubmit}
        action=""
        class="max-w-[1260px] gap-10 flex justify-around pt-2 px-7"
    >
        <div>
            <fieldset
                class="  border-3 medium-shadow border-black pb-9 px-5 bg-gray-50 grid grid-cols-2 gap-x-10 h-fit md:px-9 md:pt-2"
            >
                <legend class="text-center px-5 font-bold rounded-sm bg"
                    >DATOS DEL ESTUDIANTE</legend
                >
                <Input
                    type="text"
                    required={true}
                    label={"Nombres"}
                    bind:value={$form.student_name}
                    error={$form.errors?.student_name}
                />
                <Input
                    type="text"
                    required={true}
                    label={"Apellidos"}
                    bind:value={$form.student_last_name}
                    error={$form.errors?.student_last_name}
                />
                <Input
                    type="date"
                    required={true}
                    label={"Fecha de nacimiento"}
                    bind:value={$form.student_date_birth}
                    error={$form.errors?.student_date_birth}
                />
                <Input
                    type="email"
                    label="Correo"
                    bind:value={$form.student_email}
                    error={$form.errors?.student_email}
                />
                <div class=" flex items-center gap-2">
                    <Input
                        type="select"
                        label={"Tipo"}
                        bind:value={$form.student_document_type}
                        error={$form.errors?.student_document_type}
                        classes={"max-w-[70px] "}
                    >
                        <option value="E">E</option>
                        <option value="V">V</option>
                    </Input>
                    <Input
                        type="number"
                        required={true}
                        label={"Cédula"}
                        bind:value={$form.student_ci}
                        error={$form.errors?.student_ci}
                        classes="w-[78%]  "
                    />
                </div>
                <Input
                    type="tel"
                    label={"Teléfono"}
                    bind:value={$form.student_phone_number}
                    error={$form.errors?.student_phone_number}
                />

                <Input
                    type="select"
                    label={"Sexo"}
                    bind:value={$form.student_sex}
                    error={$form.errors?.student_sex}
                >
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino">Femenino</option>
                </Input>
                <Input
                    type="select"
                    required={true}
                    label={"Año escolar"}
                    bind:value={$form.course_id}
                    error={$form.errors?.course_id}
                    disabled={submitStatus == "Editar"}
                >
                    {#each data.courses as course}
                        <option value={course.id}>{course.name}</option>
                    {/each}
                </Input>
                <Input
                    type="select"
                    required={true}
                    label={"Sección"}
                    bind:value={$form.section_id}
                    error={$form.errors?.section_id}
                >
                    {#each data.course_sections?.data?.[`course_${$form.course_id}`] as section}
                        <option value={section.id}>{section.name}</option>
                    {/each}
                </Input>

                <Input
                    type="textarea"
                    label={"Colegio de procedencia"}
                    bind:value={$form.student_previous_school}
                    error={$form.errors?.student_previous_school}
                />
                <Input
                    type="textarea"
                    label={"Dirección"}
                    bind:value={$form.address}
                    error={$form.errors?.address}
                />
            </fieldset>
            <fieldset
                class=" border-3 medium-shadow border-black pb-9 px-5 mt-9 bg-gray-50 grid grid-cols-2 gap-x-10 h-fit md:px-9 md:pt-2"
            >
                <legend class="text-center px-5 font-bold rounded-sm bg"
                    >EXONERACIÓN</legend
                >

                <div class="col-span-2 flex items-center gap-3 mt-4">
                    <input
                        type="checkbox"
                        id="is_exempt"
                        bind:checked={$form.is_exempt}
                        class="w-5 h-5 border-3 border-black cursor-pointer"
                    />
                    <label
                        for="is_exempt"
                        class="font-semibold text-sm cursor-pointer select-none"
                    >
                        Exonerado de pago
                        <span class="text-purple">
                            <iconify-icon icon="mdi:shield-check" class="" />
                        </span>
                    </label>
                </div>

                {#if $form.is_exempt}
                    <div class="col-span-2 grid grid-cols-2 gap-x-10">
                        <Input
                            type="number"
                            label="Porcentaje de exoneración (%)"
                            bind:value={$form.exemption_percentage}
                            error={$form.errors?.exemption_percentage}
                            min="1"
                            max="100"
                        />
                        <Input
                            type="textarea"
                            label="Observación (opcional)"
                            bind:value={$form.exemption_observations}
                            error={$form.errors?.exemption_observations}
                        />
                    </div>

                    <div class="col-span-2 flex items-center gap-3 mt-2">
                        <input
                            type="checkbox"
                            id="apply_to_past_debts"
                            bind:checked={$form.apply_to_past_debts}
                            class="w-5 h-5 border-3 border-black cursor-pointer"
                        />
                        <label
                            for="apply_to_past_debts"
                            class="font-semibold text-sm cursor-pointer select-none"
                        >
                            Aplicar a deudas anteriores
                        </label>
                    </div>
                {/if}
            </fieldset>
        </div>

        <div>
            <fieldset
                class=" border-3 medium-shadow border-black pb-9 px-5 bg-gray-50 grid grid-cols-2 gap-x-10 h-fit md:px-9 md:pt-2"
            >
                <legend class="text-center px-5 font-bold rounded-sm bg"
                    >REPRESENTANTE LEGAL</legend
                >
                <div class=" flex items-center gap-2">
                    <Input
                        type="select"
                        label={"Tipo"}
                        bind:value={$form.rep_document_type}
                        error={$form.errors?.rep_document_type}
                       classes={"max-w-[70px] "}
                    >
                        <option value="E">E</option>
                        <option value="V">V</option>
                    </Input>
                    <Input
                        type="number"
                        required={true}
                        label={"Cédula"}
                        bind:value={$form.rep_ci}
                        error={$form.errors?.rep_ci}
                        classes="w-[78%]  "
                        on:input={(e) => search_rep1(e.target.value)}
                    />
                </div>

                <Input
                    type="text"
                    required={true}
                    label={"Nombres"}
                    bind:value={$form.rep_name}
                    error={$form.errors?.rep_name}
                />
                <Input
                    type="text"
                    required={true}
                    label={"Apellidos"}
                    bind:value={$form.rep_last_name}
                    error={$form.errors?.rep_last_name}
                />

                <Input
                    type="text"
                    label={"Parentesco"}
                    bind:value={$form.rep_relationship}
                    error={$form.errors?.rep_relationship}
                />

                <!-- <Input
                    type="date"
                    label={"Fecha de nacimiento"}
                    bind:value={$form.rep_date_birth}
                    error={$form.errors?.rep_date_birth}
                /> -->
                <Input
                    type="email"
                    required={true}
                    label="Correo"
                    bind:value={$form.rep_email}
                    error={$form.errors?.rep_email}
                />
                <Input
                    type="tel"
                    required={false}
                    label={"Teléfono"}
                    bind:value={$form.rep_phone_number}
                    error={$form.errors?.rep_phone_number}
                />
                <Input
                    type="tel"
                    required={false}
                    label={"Teléfono 2"}
                    bind:value={$form.rep_phone_number2}
                    error={$form.errors?.rep_phone_number2}
                />

                <!-- <Input
                    type="text"
                    label={"Profesión"}
                    bind:value={$form.rep_profession}
                    error={$form.errors?.rep_profession}
                />

                <Input
                    type="textarea"
                    label={"Lugar de trabajo"}
                    bind:value={$form.rep_workplace}
                    error={$form.errors?.rep_workplace}
                /> -->
            </fieldset>

            <fieldset
                class=" border-3 medium-shadow border-black pb-9 px-5 mt-9 bg-gray-50 grid grid-cols-2 gap-x-10 h-fit md:px-9 md:pt-2"
            >
                <legend class="text-center px-5 font-bold rounded-sm bg"
                    >SEGUNDO REPRESENTANTE</legend
                >

                <div class=" flex items-center gap-2">
                    <Input
                        type="select"
                        label={"Tipo"}
                        bind:value={$form.second_rep_document_type}
                        error={$form.errors?.second_rep_document_type}
                       classes={"max-w-[70px] "}
                    >
                        <option value="E">E</option>
                        <option value="V">V</option>
                    </Input>
                    <Input
                        type="number"
                        label={"Cédula"}
                        bind:value={$form.second_rep_ci}
                        error={$form.errors?.second_rep_ci}
                        classes="w-[78%]  "
                        on:input={(e) => search_rep1(e.target.value)}
                    />
                </div>
                <Input
                    type="text"
                    label={"Nombres"}
                    bind:value={$form.second_rep_name}
                    error={$form.errors?.second_rep_name}
                />
                <Input
                    type="text"
                    label={"Apellidos"}
                    bind:value={$form.second_rep_last_name}
                    error={$form.errors?.second_rep_last_name}
                />

                <Input
                    type="text"
                    label={"Parentesco"}
                    bind:value={$form.second_rep_relationship}
                    error={$form.errors?.second_rep_relationship}
                />
                <!-- <Input
                    type="date"
                    label={"Fecha de nacimiento"}
                    bind:value={$form.second_rep_date_birth}
                    error={$form.errors?.second_rep_date_birth}
                /> -->
                <Input
                    type="email"
                    label="Correo"
                    bind:value={$form.second_rep_email}
                    error={$form.errors?.second_rep_email}
                />

                <Input
                    type="tel"
                    label={"Teléfono"}
                    bind:value={$form.second_rep_phone_number}
                    error={$form.errors?.second_rep_phone_number}
                />

                <Input
                    type="tel"
                    label={"Teléfono 2"}
                    bind:value={$form.second_rep_phone_number2}
                    error={$form.errors?.second_rep_phone_number2}
                />
                <!-- <Input
                    type="text"
                    label={"Profesión"}
                    bind:value={$form.second_rep_profession}
                    error={$form.errors?.second_rep_profession}
                />

                <Input
                    type="textarea"
                    label={"Lugar de trabajo"}
                    bind:value={$form.second_rep_workplace}
                    error={$form.errors?.second_rep_workplace}
                /> -->
            </fieldset>
        </div>
    </form>
    <button
        form="a-form"
        slot="btn_footer"
        type="submit"
        class="animated-button w-1/2 mr-7 flex items-center justify-center gap-3"
        disabled={$form.processing}
    >
        {#if $form.processing}
            Cargando...
        {:else}
            <iconify-icon
                icon="material-symbols:save-sharp"
                width="24"
                height="24"
            />
            <span> {submitStatus === "Crear" ? "Crear" : "Editar"} </span>
        {/if}
    </button>
</Modal>

<div class="flex justify-between items-center">
    <div class="w-44">
        <Input
            id="filterYear"
            type="select"
            value={selectedCourseId}
            on:change={(e) => {
                console.log("Cambiando año a:", e.target.value);
                changeYear(e.target.value);
            }}
        >
            {#each data.courses as course}
                <option class="bg-gray-50" value={course.id.toString()}
                    >{course.name}</option
                >
            {/each}
        </Input>
    </div>
    <button
        class="btn inline-block"
        on:click={(e) => {
            e.preventDefault();
            if (submitStatus === "Editar") {
                $form.reset();
                submitStatus = "Crear";
                editingStudentId = null;
                selectedRow = { status: false, data: null };
            } else {
                $form.section_id = +data.filters.section_id;
                $form.course_id = +data.filters.course_id;
            }

            showModal = true;
        }}>Inscribir</button
    >
</div>

<Search />

<Table
    {selectedRow}
    on:fillFormToEdit={fillFormToEdit}
    on:clickDeleteIcon={() => {
        handleDelete(selectedRow.data.student_id);
    }}
    otherSelectOptions={[
        {
            label: "Reinscribir",
            icon: "mdi:school",
            classes: "bg-purple",
            onClick: handleInscribeClick,
        },
    ]}
    serverSideData={{ filters: data.filters }}
    filtersOptions={{ section_id: sectionsOfThisYear }}
    pagination={false}
>
    <div slot="filterBox">
        {#if lastSectionId < 6}
            <button
                on:click={() => createSection()}
                class="btn-ghost px-4 py-2"
            >
                Crear sección
            </button>
        {/if}

        {#if sectionsOfThisYear.length !== 1 && lastSectionId == data.filters.section_id}
            <button
                on:click={() => deleteSection(data.filters.section_id)}
                class="ml-3 p-2 px-3 bg-gray-100"
                title="Elimar Sección"
            >
                <iconify-icon class="text-xl relative top-1" icon="ph:trash"
                ></iconify-icon>
            </button>
        {/if}
    </div>
    <thead slot="thead" class="sticky top-0 z-40">
        <tr>
            <th>N°</th>
            <th>Nombres</th>
            <th>Apellidos</th>
            <th>C.I</th>
            <th>Sexo</th>
            <th>Edad</th>
            <th>Rep Legal</th>
            <th>Tel rep legal</th>
        </tr>
    </thead>

    <tbody slot="tbody">
        {#each data.students.data as row, i}
            <SelectableRow
                rowData={row}
                idKey="student_id"
                {selectedRow}
                activeClass="bg-yellow bg-opacity-10 brightness-110"
                on:select={(e) => {
                    selectedRow = e.detail;
                }}
            >
                <td>{i + 1}</td>
                <td>
                    <div class="relative max-w-fit">
                        <span>
                            {row.student_name}
                        </span>
                        {#if row.is_exempt}
                            <div
                                class="text-purple font-bold absolute -bottom-3 left-0 flex items-center gap-1 text-xs"
                            >
                                <iconify-icon
                                    icon="mdi:shield-check"
                                    class="text-purple mr-1"
                                />
                                <small>
                                    {row.exemption_percentage}%
                                </small>
                            </div>
                        {/if}
                    </div>
                </td>
                <td>{row.student_last_name}</td>
                <td>
                    <span class="">
                        {#if row.student_document_type}
                            <span class="text-xs">{row.student_document_type}-</span>
                        {/if}{row.student_ci}
                    </span>
                </td>
                <td>{row.student_sex}</td>
                <td>{row.student_age}</td>
                <td>{row.rep_name} {row.rep_last_name}</td>
                <td>{row.rep_phone_number}</td>
            </SelectableRow>
        {/each}
    </tbody>
</Table>

<style>
    fieldset {
        background-color: #fffdf5;
        background-image: url("https://www.transparenttextures.com/patterns/rice-paper-2.png");
        /* This is mostly intended for prototyping; please download the pattern and re-host for production environments. Thank you! */
    }
</style>
