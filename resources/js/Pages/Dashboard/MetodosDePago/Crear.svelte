<script>
    import Input from "../../../components/Input.svelte";
    import { useForm, router } from "@inertiajs/svelte";
    import ColorsPayMethods from "../../../components/ColorsPayMethods";
    import Alert from "../../../components/Alert.svelte";
    import { displayAlert } from "../../../stores/alertStore";

    export let data;
    let objFileds = {};
    data?.fields?.forEach((field) => {
        objFileds[field] = "";
    });
    console.log(objFileds);

    let formCreate = useForm({
        ...objFileds,
        payment_method_id: data.method.id,
    });
    function handleSubmit(event) {
        console.log("enviando");
        event.preventDefault();
        $formCreate.clearErrors();
        $formCreate.post("/dashboard/configuracion/crear-cuenta", {
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
            onSuccess: (mensaje) => {
                $formCreate.reset();
                displayAlert({
                    type: "success",
                    message: `${data.method.name} añadido correctamente`,
                });
                showModal = false;
            },
        });
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
            class={`border-l-4 border-${ColorsPayMethods()[data.method.name]} inline pl-3`}
        >
            Nuevo método: <b>{data.method.name}</b>
        </h2>
    </header>
    <div class="p-10 pt-5">
        {#if objFileds.hasOwnProperty("cash_currency")}
            <Input
                type="text"
                required={true}
                label={"Tipo de moneda"}
                bind:value={$formCreate.cash_currency}
                error={$formCreate.errors?.cash_currency}
            />
        {/if}
        {#if objFileds.hasOwnProperty("bank")}
            <Input
                type="text"
                required={true}
                label={"Banco"}
                bind:value={$formCreate.bank}
                error={$formCreate.errors?.bank}
            />
        {/if}
        {#if objFileds.hasOwnProperty("account_number")}
            <Input
                type="text"
                required={true}
                label={"Nro de cuenta Bancaria"}
                bind:value={$formCreate.account_number}
                error={$formCreate.errors?.account_number}
            />
        {/if}
        {#if objFileds.hasOwnProperty("phone_number")}
            <Input
                type="text"
                required={true}
                label={"Teléfono"}
                bind:value={$formCreate.phone_number}
                error={$formCreate.errors?.phone_number}
            />
        {/if}
        {#if objFileds.hasOwnProperty("ci")}
            <Input
                type="text"
                required={true}
                label={"Cédula"}
                bind:value={$formCreate.ci}
                error={$formCreate.errors?.ci}
            />
        {/if}
        {#if objFileds.hasOwnProperty("person_name")}
            <Input
                type="text"
                required={true}
                label={"Persona titular"}
                bind:value={$formCreate.person_name}
                error={$formCreate.errors?.person_name}
            />
        {/if}

        {#if objFileds.hasOwnProperty("username")}
            <Input
                type="text"
                required={true}
                label={"Nombre de usuario"}
                bind:value={$formCreate.username}
                error={$formCreate.errors?.username}
            />
        {/if}
        {#if objFileds.hasOwnProperty("email")}
            <Input
                type="text"
                required={true}
                label={"Correo"}
                bind:value={$formCreate.email}
                error={$formCreate.errors?.email}
            />
        {/if}
        {#if objFileds.hasOwnProperty("comision")}
            <Input
                type="text"
                required={true}
                label={"Comisión (%)"}
                bind:value={$formCreate.comision}
                error={$formCreate.errors?.comision}
            />
        {/if}
        <button
            form="a-form"
            type="submit"
            class="animated-button w-full mt-6 mr-7 flex items-center justify-center gap-3"
            disabled={$formCreate.processing}
        >
            {#if $formCreate.processing}
                Cargando...
            {:else}
                <iconify-icon
                class="text"
                    icon="material-symbols:save-sharp"
                    width="24"
                    height="24"
                />
                <span class="text"> Guardar </span>
                <span class="circle"></span>

            {/if}
        </button>
    </div>
</form>
