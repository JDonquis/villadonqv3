<script>
    import { useForm } from "@inertiajs/svelte";
    import Input from "../../components/Input.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";

    export let data = {};

    let photoPreview = data.photo_url || null;

    let form = useForm({
        name: data.name || "",
        last_name: data.last_name || "",
        document_type: data.document_type || "V",
        ci: data.ci || "",
        email: data.email || "",
        phone_number: data.phone_number || "",
        phone_number2: data.phone_number2 || "",
        address: data.address || "",
        state: data.state || "",
        city: data.city || "",
        photo: null,
        profession: data.profession || "",
        workplace: data.workplace || "",
        relationship: data.relationship || "",
    });

    let passForm = useForm({
        current_password: "",
        new_password: "",
        new_password_confirmation: "",
    });

    function onPhotoChange(event) {
        const file = event.target.files[0];
        if (!file) return;
        $form.photo = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();
        $form.post("/dashboard/perfil", {
            onSuccess: () => {
                $form.photo = null;
                displayAlert({
                    type: "success",
                    message: "Perfil actualizado correctamente.",
                });
            },
            onError: (errors) => {
                if (errors.message) {
                    displayAlert({ type: "error", message: errors.message });
                }
            },
        });
    }

    function handlePassword(event) {
        event.preventDefault();
        $passForm.clearErrors();
        $passForm.post("/dashboard/perfil/cambiar-contrasena", {
            onSuccess: () => {
                $passForm.reset();
                displayAlert({
                    type: "success",
                    message: "Contraseña actualizada correctamente.",
                });
            },
            onError: (errors) => {
                if (errors.current_password) {
                    displayAlert({
                        type: "error",
                        message: errors.current_password,
                    });
                }
            },
        });
    }
</script>

<svelte:head>
    <title>Mi Perfil</title>
</svelte:head>

<Alert />

<div class="w-full bg-white shadow-lg p-6 rounded-md max-w-[1000px] flex flex-col gap-6">
    <h3 class="text-lg font-bold text-gray-800 tracking-tight">Mi Perfil</h3>

    <div class="flex items-center gap-6">
        {#if photoPreview}
            <img
                src={photoPreview}
                alt="Foto de perfil"
                class="w-24 h-24 rounded-full object-cover border border-gray-200"
            />
        {:else}
            <div
                class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center text-gray-400 text-4xl"
            >
                <iconify-icon icon="solar:user-broken"></iconify-icon>
            </div>
        {/if}
        <div>
            <label
                for="photo-input"
                class="cursor-pointer inline-block bg-color1 text-white px-4 py-2 rounded-md text-sm hover:opacity-90"
            >
                Cambiar foto
            </label>
            <input
                id="photo-input"
                type="file"
                accept="image/*"
                class="hidden"
                on:change={onPhotoChange}
            />
            {#if $form.errors.photo}
                <p class="text-red-600 text-xs mt-1">{$form.errors.photo}</p>
            {/if}
        </div>
    </div>

    <form
        on:submit={handleSubmit}
        class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2"
    >
        <Input
            type="text"
            required={true}
            label={"Nombres"}
            bind:value={$form.name}
            error={$form.errors?.name}
        />
        <Input
            type="text"
            required={true}
            label={"Apellidos"}
            bind:value={$form.last_name}
            error={$form.errors?.last_name}
        />
        <div class="flex items-center gap-2">
            <Input
                type="select"
                label={"Tipo"}
                bind:value={$form.document_type}
                error={$form.errors?.document_type}
                classes={"max-w-[70px]"}
            >
                <option value="E">E</option>
                <option value="V">V</option>
            </Input>
            <Input
                type="number"
                required={true}
                label={"Cédula"}
                bind:value={$form.ci}
                error={$form.errors?.ci}
                classes="w-[78%]"
            />
        </div>
        <Input
            type="email"
            label="Correo"
            bind:value={$form.email}
            error={$form.errors?.email}
        />
        <Input
            type="tel"
            label={"Teléfono"}
            bind:value={$form.phone_number}
            error={$form.errors?.phone_number}
        />
        <Input
            type="tel"
            label={"Teléfono 2"}
            bind:value={$form.phone_number2}
            error={$form.errors?.phone_number2}
        />
        <Input
            type="text"
            label={"Dirección"}
            bind:value={$form.address}
            error={$form.errors?.address}
        />
        <Input
            type="text"
            label={"Estado"}
            bind:value={$form.state}
            error={$form.errors?.state}
        />
        <Input
            type="text"
            label={"Ciudad"}
            bind:value={$form.city}
            error={$form.errors?.city}
        />

        {#if data.is_representative}
            <Input
                type="text"
                label={"Profesión"}
                bind:value={$form.profession}
                error={$form.errors?.profession}
            />
            <Input
                type="text"
                label={"Lugar de trabajo"}
                bind:value={$form.workplace}
                error={$form.errors?.workplace}
            />
            <Input
                type="text"
                label={"Parentesco"}
                bind:value={$form.relationship}
                error={$form.errors?.relationship}
            />
        {/if}

        <div class="md:col-span-2 mt-4">
            <button
                type="submit"
                class="bg-color1 text-white px-6 py-2 rounded-md text-sm hover:opacity-90"
                disabled={$form.processing}
            >
                {#if $form.processing}Cargando...{:else}Guardar cambios{/if}
            </button>
        </div>
    </form>

    <div class="border-t border-gray-200 pt-6 flex flex-col gap-4">
        <h4 class="text-base font-bold text-gray-800">Cambiar contraseña</h4>

        <form
            on:submit={handlePassword}
            class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-2"
        >
            <Input
                type="password"
                required={true}
                label={"Contraseña actual"}
                bind:value={$passForm.current_password}
                error={$passForm.errors?.current_password}
            />
            <Input
                type="password"
                required={true}
                label={"Nueva contraseña"}
                bind:value={$passForm.new_password}
                error={$passForm.errors?.new_password}
            />
            <Input
                type="password"
                required={true}
                label={"Confirmar nueva contraseña"}
                bind:value={$passForm.new_password_confirmation}
                error={$passForm.errors?.new_password_confirmation}
            />

            <div class="md:col-span-3 mt-4">
                <button
                    type="submit"
                    class="bg-color1 text-white px-6 py-2 rounded-md text-sm hover:opacity-90"
                    disabled={$passForm.processing}
                >
                    {#if $passForm.processing}
                        Cargando...
                    {:else}
                        Cambiar contraseña
                    {/if}
                </button>
            </div>
        </form>
    </div>
</div>
