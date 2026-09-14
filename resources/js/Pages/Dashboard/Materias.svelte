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

    let form = useForm({ name: "" });

    let showModal = false;
    let selectedRow = { status: false, data: null };
    let editingMatterId = null;
    let submitStatus = "Crear";

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, data: null };
            editingMatterId = null;
            showModal = false;
        }
    });

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();

        if (submitStatus === "Crear") {
            $form.post("/dashboard/materias", {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({ type: "error", message: errors.message });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Materia creada correctamente",
                    });
                    showModal = false;
                },
            });
        } else {
            $form.put(`/dashboard/materias/${editingMatterId}`, {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({ type: "error", message: errors.message });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Materia actualizada correctamente",
                    });
                    showModal = false;
                    editingMatterId = null;
                    submitStatus = "Crear";
                    selectedRow = { status: false, data: null };
                },
            });
        }
    }

    function fillFormToEdit() {
        const matter = selectedRow.data;
        editingMatterId = matter.id;
        submitStatus = "Editar";
        $form.name = matter.name;
        showModal = true;
    }

    function handleDelete() {
        if (!selectedRow.data) return;
        if (!confirm(`¿Está seguro de eliminar la materia "${selectedRow.data.name}"?`))
            return;

        router.delete(`/dashboard/materias/${selectedRow.data.id}`, {
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Materia eliminada correctamente",
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
</script>

<svelte:head>
    <title>Materias</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-3">
    <h2 class="text-2xl font-bold text-color1">Materias</h2>
    <button
        class="animated-button w-fitcontent min-w-[250px] flex items-center justify-center gap-3"
        on:click={(e) => {
            e.preventDefault();
            $form.reset();
            submitStatus = "Crear";
            editingMatterId = null;
            showModal = true;
        }}
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
        <iconify-icon icon="line-md:plus" class="text" width="20" height="20"></iconify-icon>
        <span class="text">Nueva materia</span>
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
</div>

<Table
    {selectedRow}
    allowFilters={false}
    filtersOptions={{}}
    serverSideData={{ filters: {} }}
    pagination={false}
    on:fillFormToEdit={fillFormToEdit}
    on:clickDeleteIcon={handleDelete}
>
    <thead slot="thead" class="sticky top-0 z-50">
        <tr>
            <th>N°</th>
            <th>Nombre</th>
            <th>Profesores asignados</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each data.matters as matter, i}
            <SelectableRow
                rowData={matter}
                idKey="id"
                {selectedRow}
                activeClass="bg-yellow bg-opacity-10 brightness-110"
                on:select={(e) => {
                    selectedRow = e.detail;
                }}
            >
                <td>{i + 1}</td>
                <td>{matter.name}</td>
                <td>{matter.teachers_count}</td>
            </SelectableRow>
        {/each}
    </tbody>
</Table>

<Modal bind:showModal classes={"w-96"}>
    <form
        id="m-form"
        on:submit={handleSubmit}
        action=""
        class="pt-2 px-5"
    >
        <Input
            label="Nombre de la materia"
            type="text"
            required={true}
            bind:value={$form.name}
            error={$form.errors.name}
        />
    </form>
    <button
        form="m-form"
        slot="btn_footer"
        type="submit"
        class="animated-button min-w-[200px] flex items-center justify-center gap-3"
        disabled={$form.processing}
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
        {#if $form.processing}
            <span class="text">Cargando...</span>
        {:else}
            <iconify-icon
                icon="material-symbols:save-sharp"
                class="text"
                width="24"
                height="24"
            />
            <span class="text">{submitStatus === "Crear" ? "Crear" : "Guardar"}</span>
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
</Modal>
