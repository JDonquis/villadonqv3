<script>
    import { useForm } from "@inertiajs/svelte";
    import Input from "../components/Input.svelte";
    import Alert from "../components/Alert.svelte";
    import { displayAlert } from "../stores/alertStore";

    export let sent = false;
    export let errors = {};

    let form = useForm({
        email: null,
    });

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();
        $form.post("/olvidar-contrasena", {
            onSuccess: () => {
                sent = true;
            },
            onError: (err) => {
                if (err.email) {
                    displayAlert({ type: "error", message: err.email });
                }
            },
        });
    }

    function goBack() {
        window.location.href = "/";
    }
</script>

<Alert />
<section class="bg-background min-h-screen flex items-center justify-center">
    <div class="bg-purple p-8  border-4 border-black medium-shadow  shadow-lg w-full max-w-md">
     <div class="flex justify-center mb-2">
        <img
             src="/img/Isotipo-villadonq-blanco.png"
             alt="Villadonq"
             class="w-10 mx-auto   text-center h-10 object-contain inline"
         />

    </div>
        {#if sent}
            <div class="text-center">
                <h1 class="text-2xl font-bold text-black mb-4">Correo Enviado</h1>
                <p class="text-black mb-6">
                    Si el correo electrónico está registrado en nuestro sistema, recibirás un mensaje con instrucciones para restablecer tu contraseña.
                </p>
                <p class="text-yellow text-sm mb-6">
                    No compartas este enlace con nadie.
                </p>
                <button on:click={goBack} class="btn_create w-full">
                    Volver al Login
                </button>
            </div>
        {:else}

            <h1 class="text-2xl font-bold text-white text-center mb-2">¿Olvidaste tu Contraseña?</h1>
            <p class="text-black text-center mb-6">
                Ingresa el correo electrónico de tu cuenta y te enviaremos un enlace para restablecer tu contraseña.
            </p>
            
            <form on:submit={handleSubmit} class="space-y-4">
                <div>
                    <Input
                        type="text"
                        name="email"
                        label="Correo"
                        required={true}
                        bind:value={$form.email}
                        error={$form.errors?.email}
                    />
                </div>
                
                <button
                    type="submit"
                    class="btn w-full mt-4"
                    disabled={$form.processing}
                >
                    {$form.processing ? 'Enviando...' : 'Enviar Enlace de Recuperación'}
                </button>

                <button
                    type="button"
                    on:click={goBack}
                    class="text-white hover:text-yellow text-sm w-full mt-2"
                >
                    ← Volver al Login
                </button>
            </form>
        {/if}
    </div>
</section>

<style>
    * {
        box-sizing: border-box;
    }
    button[type="submit"] {
        background-color: #490A75;
        color: white;
    }
    
</style>
