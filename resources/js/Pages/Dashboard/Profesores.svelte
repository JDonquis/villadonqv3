<script>
    import { useForm } from "@inertiajs/svelte";
    import { router } from "@inertiajs/svelte";
    import Input from "../../components/Input.svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";

    export let data = [];

    const emptyForm = {
        type_user_id: 3,
        ci: "",
        name: "",
        last_name: "",
        email: "",
        phone_number: "",
        address: "",
        matters: [],
    };

    let form = useForm({ ...emptyForm });

    let showModal = false;
    let selectedRow = { status: false, data: null };
    let editingTeacherId = null;
    let submitStatus = "Crear";

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, data: null };
            editingTeacherId = null;
            showModal = false;
        }
    });

    function toggleMatter(id) {
        const current = [...$form.matters];
        const idx = current.indexOf(id);
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(id);
        }
        $form.matters = current;
    }

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();

        if (submitStatus === "Crear") {
            $form.post("/dashboard/profesores", {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({ type: "error", message: errors.message });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Profesor creado correctamente",
                    });
                    showModal = false;
                    editingTeacherId = null;
                },
            });
        } else {
            $form.put(`/dashboard/profesores/${editingTeacherId}`, {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({ type: "error", message: errors.message });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Profesor actualizado correctamente",
                    });
                    showModal = false;
                    editingTeacherId = null;
                    submitStatus = "Crear";
                    selectedRow = { status: false, data: null };
                },
            });
        }
    }

    function fillFormToEdit() {
        const teacher = selectedRow.data;
        editingTeacherId = teacher.id;
        submitStatus = "Editar";
        $form.type_user_id = 3;
        $form.ci = teacher.ci;
        $form.name = teacher.name;
        $form.last_name = teacher.last_name;
        $form.email = teacher.email;
        $form.phone_number = teacher.phone_number || "";
        $form.address = teacher.address || "";
        $form.matters = [...(teacher.matter_ids || [])];
        showModal = true;
    }

    function handleDelete() {
        if (!selectedRow.data) return;
        if (
            !confirm(
                `¿Está seguro de eliminar a ${selectedRow.data.name} ${selectedRow.data.last_name}?`,
            )
        )
            return;

        router.delete(`/dashboard/profesores/${selectedRow.data.id}`, {
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Profesor eliminado correctamente",
                });
                selectedRow = { status: false, data: null };
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al eliminar",
                });
            },
        });
    }

    function handleResendEmail() {
        if (!selectedRow.data) return;
        router.post(
            `/dashboard/profesores/${selectedRow.data.id}/reenviar-correo`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    displayAlert({
                        type: "success",
                        message: "Correo reenviado correctamente",
                    });
                    selectedRow = { status: false, data: null };
                },
                onError: (errors) => {
                    displayAlert({
                        type: "error",
                        message: errors.message || "Error al reenviar el correo",
                    });
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Profesores</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-3">
    <button
        class="animated-button w-fitcontent ml-auto"
        on:click={(e) => {
            e.preventDefault();
            $form.reset();
            submitStatus = "Crear";
            editingTeacherId = null;
            showModal = true;
        }}
    >
        <span class="text">Nuevo profesor</span>
        <span class="circle"></span>
    </button>
</div>

<Table
    {selectedRow}
    allowFilters={false}
    filtersOptions={{}}
    serverSideData={{ filters: {} }}
    pagination={false}
    on:fillFormToEdit={fillFormToEdit}
    on:clickDeleteIcon={handleDelete}
    otherSelectOptions={[
        {
            label: "Reenviar correo",
            icon: "material-symbols:mail-outline",
            classes: "bg-blue text-white",
            onClick: handleResendEmail,
        },
    ]}
>
    <thead slot="thead" class="sticky top-0 z-50">
        <tr>
            <th>N°</th>
            <th>Nombres</th>
            <th>Apellidos</th>
            <th>Cédula</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Materias</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each data.teachers as teacher, i}
            <SelectableRow
                rowData={teacher}
                idKey="id"
                {selectedRow}
                activeClass="bg-yellow bg-opacity-10 brightness-110"
                on:select={(e) => {
                    selectedRow = e.detail;
                }}
            >
                <td>{i + 1}</td>
                <td>{teacher.name}</td>
                <td>{teacher.last_name}</td>
                <td>{teacher.ci}</td>
                <td>{teacher.email}</td>
                <td>{teacher.phone_number}</td>
                <td class="max-w-[220px]">
                    <div class="flex flex-wrap gap-1">
                        {#if teacher.matters?.length}
                            {#each teacher.matters as matter}
                                <span
                                    class="text-xs px-2 py-0.5 bg-color1/10 text-color1 rounded-full"
                                    >{matter}</span
                                >
                            {/each}
                        {:else}
                            <span class="text-gray-400 text-xs">Sin materias</span>
                        {/if}
                    </div>
                </td>
            </SelectableRow>
        {/each}
    </tbody>
</Table>

<Modal bind:showModal classes={"w-fit"}>
    <form
        id="p-form"
        on:submit={handleSubmit}
        action=""
        class="max-w-[760px] grid grid-cols-2 gap-x-8 gap-y-1 pt-2 px-7"
    >
        <Input
            label="Nombre"
            type="text"
            required={true}
            bind:value={$form.name}
            error={$form.errors.name}
        />
        <Input
            label="Apellido"
            type="text"
            required={true}
            bind:value={$form.last_name}
            error={$form.errors.last_name}
        />
        <Input
            label="Cédula"
            type="text"
            required={true}
            bind:value={$form.ci}
            error={$form.errors.ci}
        />
        <Input
            label="Correo electrónico"
            type="email"
            required={true}
            bind:value={$form.email}
            error={$form.errors.email}
        />
        <Input
            label="Teléfono"
            type="text"
            bind:value={$form.phone_number}
            error={$form.errors.phone_number}
        />
        <Input
            label="Dirección"
            type="textarea"
            bind:value={$form.address}
            error={$form.errors.address}
        />

        <div class="col-span-2 mt-4">
            <p class="text-xs md:text-sm font-semibold text-gray-700 mb-2">
                Materias asignadas
            </p>
            {#if data.matters?.length}
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-56 overflow-auto">
                    {#each data.matters as matter}
                        <label
                            class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-md border {$form.matters.includes(matter.id) ? 'border-color2/50 bg-color1/5' : 'border-gray-200'}"
                        >
                            <input
                                type="checkbox"
                                checked={$form.matters.includes(matter.id)}
                                on:change={() => toggleMatter(matter.id)}
                                class="w-4 h-4"
                            />
                            {matter.name}
                        </label>
                    {/each}
                </div>
            {:else}
                <p class="text-sm text-gray-400">
                    No hay materias creadas. Crea materias en el módulo
                    "Materias".
                </p>
            {/if}
            {#if $form.errors.matters}
                <p class="text-red text-xs font-semibold mt-1">
                    {$form.errors.matters}
                </p>
            {/if}
        </div>
    </form>
    <button
        form="p-form"
        slot="btn_footer"
        type="submit"
        class="animated-button min-w-[200px] flex gap-2"
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
            <span>{submitStatus === "Crear" ? "Crear" : "Guardar"}</span>
        {/if}
    </button>
</Modal>
