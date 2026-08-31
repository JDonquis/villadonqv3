<script>
    import { useForm } from "@inertiajs/svelte";
    import { router } from "@inertiajs/svelte";
    import Input from "../../components/Input.svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";
    import { page } from "@inertiajs/svelte";
    import ImportResultModal from "../../components/ImportResultModal.svelte";
    import axios from "axios";
    console.log($page);
    export let types = [];
    export let data = [];
    let submitStatus = "Crear";

    let importFileInput = null;
    let showImportResult = false;
    let importSummary = { created: 0, errors: [] };

    async function handleImportFile(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append("file", file);
        try {
            const { data } = await axios.post(
                "/dashboard/personal/importar",
                formData,
                { headers: { Accept: "application/json" } }
            );
            importSummary = data;
            showImportResult = true;
        } catch (err) {
            displayAlert({
                type: "error",
                message:
                    err.response?.data?.error ||
                    err.response?.data?.errors?.file?.[0] ||
                    "No se pudo importar el archivo.",
            });
        } finally {
            if (importFileInput) importFileInput.value = "";
        }
    }

    console.log(data);
    const emptyDataForm = {
        type_user_id: 1,
        ci: "",
        name: "",
        last_name: "",
        email: "",
        phone_number: "",
        address: "",
        is_admin: false,
        email_verified_status: false,
    };

    let form = useForm({
        ...emptyDataForm,
    });

    let showModal = false;
    let selectedRow = { status: false, id: 0 };
    let editingUser = null;

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, id: 0 };
            editingUser = null;
            showModal = false;
        }
    });

    function loadUserData(user) {
        $form.reset();
        $form.fill({
            type_user_id: user.type_user_id || 1,
            ci: user.ci,
            name: user.name,
            last_name: user.last_name,
            email: user.email,
            phone_number: user.phone_number,
            address: user.address,
            is_admin: user.is_admin || false,
        });
        editingUser = user;
        submitStatus = "Editar";
        showModal = true;
    }

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();
        if (submitStatus == "Crear") {
            $form.post("/dashboard/personal", {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: () => {
                    displayAlert({
                        type: "success",
                        message: "Usuario creado correctamente",
                    });
                    showModal = false;
                    editingUser = null;
                    $form.reset();
                },
            });
        } else if (submitStatus == "Editar") {
            $form.put(`/dashboard/personal/${editingUser.id}`, {
                onError: (errors) => {
                    if (errors.message) {
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: (mensaje) => {
                    $form.reset();
                    displayAlert({
                        type: "success",
                        message: "Usuario actualizado correctamente",
                    });
                    showModal = false;
                    editingUser = null;
                    selectedRow = { status: false, id: 0 };
                },
            });
        }
    }

    function handleEdit() {
        if (!$page.props.auth.is_admin) {
            displayAlert({
                type: "error",
                message: "No tienes permisos para editar personal",
            });
            return;
        }
        showModal = true;
        editingUser = selectedRow.data;
        submitStatus = "Editar";

        const personal = selectedRow.data;
        $form.type_user_id = personal.type_user_id || 1;
        $form.ci = personal.ci;
        $form.name = personal.name;
        $form.last_name = personal.last_name;
        $form.email = personal.email;
        $form.phone_number = personal.phone_number;
        $form.address = personal.address;
        $form.is_admin = personal.is_admin || false;
        $form.email_verified_status = personal.email_verified_status || false;
    }

    function handleDelete() {
        const user = data.find((u) => u.id === selectedRow.data.id);
        if (
            user &&
            confirm(
                `¿Estás seguro de eliminar a ${user.name} ${user.last_name}?`,
            )
        ) {
            router.delete(`/dashboard/personal/${user.id}`, {
                onSuccess: () => {
                    displayAlert({
                        type: "success",
                        message: "Usuario eliminado correctamente",
                    });
                    selectedRow = { status: false, data: {} };
                },
                onError: (errors) => {
                    displayAlert({
                        type: "error",
                        message: errors.data || "Error al eliminar",
                    });
                },
            });
        }
    }

    function handleResendEmail() {
        if (!$page.props.auth.is_admin) {
            displayAlert({
                type: "error",
                message: "No tienes permisos para reenviar el correo",
            });
            return;
        }
        if (!selectedRow?.data?.id) {
            displayAlert({
                type: "error",
                message: "Selecciona un usuario para reenviar el correo",
            });
            return;
        }
        router.post(`/dashboard/personal/${selectedRow.data.id}/reenviar-correo`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Correo reenviado correctamente",
                });
                selectedRow = { status: false, data: {} };
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al reenviar el correo",
                });
            },
        });
    }
</script>

<svelte:head>
    <title>Personal</title>
</svelte:head>
<section class=" min-h-screen">
    <Alert />
    <div class=" mx-auto">
        <div class="flex justify-end items-center gap-3 mb-3">
            <input
                type="file"
                accept=".xlsx"
                class="hidden"
                bind:this={importFileInput}
                on:change={handleImportFile}
            />
            <button type="button" class="toolbar-secondary" on:click={() => importFileInput?.click()}>
                <iconify-icon icon="material-symbols:upload" width="20" height="20" />
                Importar
            </button>
            <a href="/dashboard/personal/plantilla" class="toolbar-secondary">
                <iconify-icon icon="material-symbols:download" width="20" height="20" />
                Descargar plantilla
            </a>
            <button
            class="animated-button w-fitcontent"
            on:click={(e) => {
                if (!$page.props.auth.is_admin) {
                    displayAlert({
                        type: "error",
                        message: "No tienes permisos para crear personal",
                    });
                    return;
                }
                if (submitStatus === "Editar") {
                    $form.reset();
                    editingUser = null;
                    selectedRow = { status: false, data: {} };
                }
                submitStatus = "Crear";
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
            <span class="text">Nuevo personal</span>
            <span class="circle"></span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="arr-1"
                viewBox="0 0 24 24"
            >
                <path
                    d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                ></path>
            </svg></button
        >
        </div>
        <!-- List -->

        <Table
            {selectedRow}
            allowFilters={false}
            filtersOptions={{}}
            serverSideData={{ filters: {} }}
            pagination={false}
            on:fillFormToEdit={() => {
                if (!$page.props.auth.is_admin) {
                    displayAlert({
                        type: "error",
                        message: "No tienes permisos para editar personal",
                    });
                    return;
                }
                handleEdit();
            }}
            on:clickDeleteIcon={handleDelete}
            otherSelectOptions={[
                {
                    onClick: () => handleResendEmail(),
                    classes: "bg-blue text-white",
                    label: "Reenviar correo",
                    icon: "material-symbols:mail-outline",
                },
            ]}
        >
            <thead slot="thead" class="sticky top-0 z-50">
                <tr>
                    <th>N°</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Cédula de identidad</th>
                    <th>Correo electrónico</th>
                    <th>Número de teléfono</th>
                    <th class="max-w-[200px]">Dirección</th>
                    <th class="max-w-[200px]">Es administrador</th>
                </tr>
            </thead>
            <tbody slot="tbody">
                {#each data as user}
                    <SelectableRow
                        rowData={user}
                        idKey="id"
                        {selectedRow}
                        activeClass="bg-gray-200"
                        inactiveClass="hover:bg-gray-100"
                        on:select={(e) => {
                            selectedRow = e.detail;
                        }}
                    >
                        <td>{user.id}</td>
                        <td>{user.name}</td>
                        <td>{user.last_name}</td>
                        <td>{user.ci}</td>
                        <td>{user.email}</td>
                        <td>{user.phone_number}</td>
                        <td class="max-w-[200px] truncate" title={user.address}
                            >{user.address}</td
                        >
                        <td class="max-w-[200px]">
                            {#if user.is_admin}
                                Si
                            {:else}
                                No
                            {/if}
                        </td>
                    </SelectableRow>
                {/each}
            </tbody>
        </Table>
    </div>
</section>

<Modal bind:showModal modalClasses={"max-w-[560px]"}>
    <form
        id="a-form"
        on:submit={handleSubmit}
        action=""
        class="max-w-[1260px] gap-x-10 grid grid-cols-2 pt-2 px-7"
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
            label="Cédula de identidad"
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
            label="Número de teléfono"
            type="text"
            required={true}
            bind:value={$form.phone_number}
            error={$form.errors.phone_number}
        />
        <Input
            label="Dirección"
            type="textarea"
            required={true}
            bind:value={$form.address}
            error={$form.errors.address}
        />

        <div class="col-span-2 flex items-center gap-2 mt-2">
            <input
                id="is_admin"
                type="checkbox"
                bind:checked={$form.is_admin}
                class="w-4 h-4 text-green bg-gray-100 border-gray-300 rounded focus:ring-green/50 focus:ring-2"
            />
            <label for="is_admin" class="text-sm font-medium text-gray-900"
                >¿Es administrador?</label
            >
        </div>
        <button
            type="submit"
            class="animated-button col-span-2 mt-7 flex items-center justify-center gap-3"
            disabled={$form.processing}
        >
            <iconify-icon
                class="text"
                icon="material-symbols:save-sharp"
                width="24"
                height="24"
            />
            {#if $form.processing}
                <span class="text"> Cargando...</span>
            {:else}
                <span class="text">Guardar</span>
            {/if}
            <span class="circle"></span>
        </button>
    </form>
</Modal>

{#if showImportResult}
    <ImportResultModal bind:show={showImportResult} summary={importSummary} />
{/if}

<style>
</style>
