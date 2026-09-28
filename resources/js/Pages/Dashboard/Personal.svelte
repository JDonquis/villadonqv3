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
    export let modules = [];
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
                { headers: { Accept: "application/json" } },
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
        modules: [],
    };

    let form = useForm({
        ...emptyDataForm,
    });

    let showModal = false;
    let selectedRow = { status: false, id: 0 };
    let editingUser = null;
    let showMobileActions = false;

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, id: 0 };
            editingUser = null;
            showModal = false;
        }
    });

    function openNuevoPersonal() {
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
    }

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
            modules: (user.modules || []).map((m) => m.id),
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
        $form.modules = (personal.modules || []).map((m) => m.id);
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
        router.post(
            `/dashboard/personal/${selectedRow.data.id}/reenviar-correo`,
            {},
            {
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
                        message:
                            errors.message || "Error al reenviar el correo",
                    });
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Personal</title>
</svelte:head>
<section class=" min-h-screen">
    <Alert />
    <h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">
        Personal
    </h2>
    <div class=" mx-auto">
        <div class="flex justify-end items-center gap-3 mb-3">
            <input
                type="file"
                accept=".xlsx"
                class="hidden"
                bind:this={importFileInput}
                on:change={handleImportFile}
            />
            <!-- Desktop: show buttons -->
            <div class="hidden md:flex items-center gap-3">
                <button
                    type="button"
                    class="toolbar-secondary opacity-50 hover:opacity-100"
                    on:click={() => importFileInput?.click()}
                >
                    <iconify-icon
                        icon="material-symbols:upload"
                        width="20"
                        height="20"
                    />
                    Importar
                </button>
                <a
                    href="/dashboard/personal/plantilla"
                    class="toolbar-secondary opacity-50 hover:opacity-100"
                >
                    <iconify-icon
                        icon="material-symbols:download "
                        width="20"
                        height="20"
                    />
                    Descargar plantilla
                </a>
            </div>
            <!-- Mobile: small button opens modal with actions -->
            <div class="md:hidden">
                <button
                    class="toolbar-secondary p-2"
                    on:click={() => (showMobileActions = true)}
                    aria-label="Más acciones"
                >
                    <iconify-icon
                        icon="material-symbols:upload"
                        width="20"
                        height="20"
                    />
                </button>
                <Modal bind:showModal={showMobileActions} classes={"w-72"}>
                    <div class="flex flex-col gap-3 p-2">
                        <button
                            type="button"
                            class="toolbar-secondary"
                            on:click={() => {
                                importFileInput?.click();
                                showMobileActions = false;
                            }}
                        >
                            <iconify-icon
                                icon="material-symbols:upload"
                                width="20"
                                height="20"
                            />
                            <span class="ml-2">Importar</span>
                        </button>
                        <a
                            href="/dashboard/personal/plantilla"
                            class="toolbar-secondary"
                            on:click={() => (showMobileActions = false)}
                        >
                            <iconify-icon
                                icon="material-symbols:download "
                                width="20"
                                height="20"
                            />
                            <span class="ml-2">Descargar plantilla</span>
                        </a>
                    </div>
                </Modal>
            </div>
            {#if $page.props.failedImportsCount > 0}
                <a
                    href="/dashboard/importaciones-fallidas-profesores"
                    class="toolbar-secondary"
                    style="background-color: #16a34a; color: white;"
                >
                    <iconify-icon
                        icon="material-symbols:error-outline"
                        width="20"
                        height="20"
                    />
                    {$page.props.failedImportsCount} error{$page.props
                        .failedImportsCount !== 1
                        ? "es"
                        : ""}
                </a>
            {/if}
            <div class="hidden sm:flex">
                <button
                    class="animated-button w-fitcontent"
                    title="Aprieta la tecla N"
                    on:click={openNuevoPersonal}
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
                    </svg>
                </button>
            </div>
            <button
                type="button"
                class="fixed-bottom-mobile fab sm:hidden bg-color1 text-white"
                title="Aprieta la tecla N"
                on:click={openNuevoPersonal}
                aria-label="Nuevo personal"
            >
                <iconify-icon icon="mdi:plus" width="26" height="26"
                ></iconify-icon>
            </button>
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

<Modal
    bind:showModal
    keyShortcut="n"
    onKeyShortcut={openNuevoPersonal}
    modalClasses={"max-w-[560px]"}
>
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

        <div class="col-span-2 mt-4">
            <label
                for="is_admin"
                class="flex items-center justify-between p-4 rounded-xl border transition-all cursor-pointer select-none {$form.is_admin
                    ? 'bg-color4/30 border-color2 ring-1 ring-color2/20'
                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'}"
            >
                <div class="flex items-center gap-3">
                    <!-- Icono de escudo / privilegio -->
                    <div
                        class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors {$form.is_admin
                            ? 'bg-color2 text-white shadow-sm'
                            : 'bg-slate-100 text-slate-500'}"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
                            />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-900"
                                >¿Es administrador?</span
                            >
                            <span
                                class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-slate-100 text-slate-600 uppercase tracking-wide"
                            >
                                Acceso Total
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Otorga control total y anula las restricciones
                            individuales de módulos.
                        </p>
                    </div>
                </div>
                <!-- Switch / Checkbox interactivo estilizado -->
                <div class="relative flex items-center">
                    <input
                        id="is_admin"
                        type="checkbox"
                        bind:checked={$form.is_admin}
                        class="sr-only peer"
                    />
                    <!-- Píldora toggle visual -->
                    <div
                        class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-color4"
                    ></div>
                </div>
            </label>
        </div>

        <!-- SECCIÓN: PERMISOS DE MÓDULOS CON TUS ICONOS-->
        <!-- ========================================== -->
        <div class="col-span-2 mt-4 border-t border-slate-100 pt-5">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-semibold text-slate-800">
                        Permisos de módulos
                    </p>
                    {#if !$form.is_admin}
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700"
                        >
                            • {$form.modules.length} seleccionados
                        </span>
                    {/if}
                </div>
                <!-- Accesos rápidos para seleccionar/limpiar cuando no es admin -->
                {#if !$form.is_admin}
                    <div
                        class="flex items-center gap-2 text-xs font-medium text-slate-500"
                    >
                        <button
                            type="button"
                            on:click={() =>
                                ($form.modules = modules.map((m) => m.id))}
                            class="hover:text-blue-600 transition-colors"
                        >
                            Seleccionar todos
                        </button> <span>•</span>
                        <button
                            type="button"
                            on:click={() => ($form.modules = [])}
                            class="hover:text-red-500 transition-colors"
                        >
                            Limpiar
                        </button>
                    </div>
                {/if}
            </div>
            <p class="text-xs text-slate-500 mb-3">
                {#if $form.is_admin}
                    <span class="text-blue-600 font-medium">
                        Administrador completo: tiene acceso a todos los módulos
                        automáticamente.
                    </span>
                {:else}
                    Selecciona los módulos a los que este colaborador podrá
                    ingresar e interactuar.
                {/if}
            </p>
            <!-- Grilla de módulos con <iconify-icon> -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                {#each modules as module (module.id)}
                    {@const isChecked = $form.modules.includes(module.id)}
                    <label
                        class="group relative flex items-center justify-between p-3 rounded-xl border transition-all select-none {$form.is_admin
                            ? 'bg-slate-50/70 border-slate-200/60 opacity-60 cursor-not-allowed'
                            : isChecked
                              ? 'bg-white border-slate-900 shadow-sm cursor-pointer'
                              : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50 cursor-pointer'}"
                    >
                        <input
                            type="checkbox"
                            bind:group={$form.modules}
                            value={module.id}
                            disabled={$form.is_admin}
                            class="sr-only"
                        />
                        <div class="flex items-center gap-2.5 min-w-0">
                            <!-- Contenedor con tu mismo icono de Iconify -->
                            <div
                                class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 transition-colors {$form.is_admin
                                    ? 'bg-slate-200/70 text-slate-500'
                                    : isChecked
                                      ? 'bg-slate-900 text-white'
                                      : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200'}"
                            >
                                {#if module.icon}
                                    <iconify-icon
                                        icon={module.icon}
                                        width="16"
                                        height="16"
                                    ></iconify-icon>
                                {/if}
                            </div>
                            <div class="truncate">
                                <p
                                    class="text-xs font-semibold text-slate-800 truncate"
                                >
                                    {module.name}
                                </p>
                            </div>
                        </div>
                        <!-- Indicador de Check cuadrado moderno -->
                        <div
                            class="w-4 h-4 rounded-md border flex items-center justify-center shrink-0 ml-2 transition-colors {$form.is_admin
                                ? 'border-slate-300 bg-slate-100'
                                : isChecked
                                  ? 'bg-slate-900 border-slate-900 text-white'
                                  : 'border-slate-300 bg-white group-hover:border-slate-400'}"
                        >
                            {#if isChecked && !$form.is_admin}
                                <svg
                                    class="w-2.5 h-2.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    stroke-width="3.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            {:else if $form.is_admin}
                                <div
                                    class="w-1.5 h-1.5 rounded-full bg-slate-400"
                                ></div>
                            {/if}
                        </div>
                    </label>
                {/each}
            </div>
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
    <ImportResultModal
        bind:show={showImportResult}
        summary={importSummary}
        importType="teacher"
    />
{/if}

<style>
</style>
