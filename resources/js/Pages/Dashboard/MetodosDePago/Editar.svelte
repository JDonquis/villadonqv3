<script>
    import Input from "../../../components/Input.svelte";
    import { useForm, router } from "@inertiajs/svelte";
    import ColorsPayMethods from "../../../components/ColorsPayMethods";
    import Alert from "../../../components/Alert.svelte";
    import { displayAlert } from "../../../stores/alertStore";

    export let data;
    console.log(data.account);

    let formData = useForm({
        ...data.account.data,
    });
    function handleSubmit(event) {
        console.log("enviando");
        event.preventDefault();
        $formData.clearErrors();
        $formData.put(
            `/dashboard/configuracion/editar-cuenta/${$formData.id}`,
            {
                preserveScroll: false,
                 onError: (errors) => {
                if (errors.message) {
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: (mensaje) => {
                    $formData.reset();
                    displayAlert({
                        type: "success",
                        message: `${data.method.name} actualizado correctamente`,
                    });
                    showModal = false;
                },
            },
        );
    }
</script>

<form
    action=""
    id="a-form"
    on:submit={handleSubmit}
    class="border-4 medium-shadow border-black p max-w-[450px] mx-auto"
>
    <header class={`bg-black/5 text-dark py-4 pl-3`}>
        <h2
            class={`border-l-8 border-${ColorsPayMethods()[data.method.name]} inline pl-3`}
        >
            Editar método: <b>{data.method.name}</b>
        </h2>
    </header>
    <div class="p-10 pt-5">
     {#if data.account.data.hasOwnProperty("cash_currency")}
            <Input
                type="text"
                required={true}
                label={"Tipo de moneda"}
                bind:value={$formData.cash_currency}
                error={$formData.errors?.cash_currency}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("bank")}
            <Input
                type="text"
                required={true}
                label={"Banco"}
                bind:value={$formData.bank}
                error={$formData.errors?.bank}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("account_number")}
            <Input
                type="text"
                required={true}
                label={"Nro de cuenta Bancaria"}
                bind:value={$formData.account_number}
                error={$formData.errors?.account_number}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("phone_number")}
            <Input
                type="text"
                required={true}
                label={"Teléfono"}
                bind:value={$formData.phone_number}
                error={$formData.errors?.phone_number}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("ci")}
            <Input
                type="text"
                required={true}
                label={"Cédula"}
                bind:value={$formData.ci}
                error={$formData.errors?.ci}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("person_name")}
            <Input
                type="text"
                required={true}
                label={"Persona titular"}
                bind:value={$formData.person_name}
                error={$formData.errors?.person_name}
            />
        {/if}

        {#if data.account.data.hasOwnProperty("username")}
            <Input
                type="text"
                required={true}
                label={"Nombre de usuario"}
                bind:value={$formData.username}
                error={$formData.errors?.username}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("email")}
            <Input
                type="text"
                required={true}
                label={"Correo"}
                bind:value={$formData.email}
                error={$formData.errors?.email}
            />
        {/if}
        {#if data.account.data.hasOwnProperty("comision")}
            <Input
                type="text"
                required={true}
                label={"Comisión (%)"}
                bind:value={$formData.comision}
                error={$formData.errors?.comision}
            />
        {/if}
        <button
            form="a-form"
            type="submit"
            class="animated-button w-full mt-6 mr-7 flex items-center justify-center gap-3"
            disabled={$formData.processing}
        >
            {#if $formData.processing}
                Cargando...
            {:else}
                <iconify-icon
                    icon="material-symbols:save-sharp"
                    width="24"
                    height="24"
                />
                <span class="text"> Guardar </span>
            {/if}
            <span class="circle"></span>

        </button>
    </div>
</form>
