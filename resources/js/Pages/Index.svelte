<script>
    import { useForm, page } from "@inertiajs/svelte";

    // import secretariaLogo from '$lib/images/logo_secretaria-emailrcle-main.png';
    import Input from "../components/Input.svelte";
    import Modal from "../components/Modal.svelte";
    import AnimatedButton from "../components/AnimatedButton.svelte";

    import Alert from "../components/Alert.svelte";
    import { displayAlert } from "../stores/alertStore";
    let showModal = false;

    let form = useForm({
        email: null,
        password: null,
    });

    let lastAlertedError = null;
    $: loginError = $page.props?.errors?.data;
    $: if (loginError && loginError !== lastAlertedError) {
        lastAlertedError = loginError;
        displayAlert({ type: "error", message: loginError });
    }

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();
        $form.post("/login", {
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
        });
    }

    setTimeout(() => {
        document.querySelector("input[name='email']").focus();
    }, 200);
</script>

<Alert />

<div class="">
    <div class="max-w-[1400px] w-11/12 mx-auto">
        <img class="mt-4 max-w-[200px]" src="/img/Logo-villadonq-azul-oscuro.png" alt="" />

        <h2
            class="text-white text-xl font-bold text-center mb-6 tracking-wide uppercase"
        >
            <img
                src="/img/Isotipo-villadonq-blanco.png"
                alt="Villadonq"
                class="w-10 h-10 object-contain inline"
            />
        </h2>
        <h1 class="leading-8">
            La forma inteligente de administrar tu colegio.
        </h1>
        <p class=" mt-3 mb-5 text-base md:text-lg max-w-[400px]">
            Matrícula, pagos y estados de cuenta en un solo sistema. Cero deudas
            perdidas.
        </p>
        <div
            class="w-full max-w-sm bg-purple/5 pt-5 backdrop-blur-md p-8 rounded-2xl border border-white/10 shadow-2xl animate-slide-up"
        >
            <form on:submit={handleSubmit} class="min-w-[270px] px-5 mt-5">
                <div class="mb-10">
                    <Input
                        type="email"
                        labelClass="text-black"
                        name="email"
                        required={true}
                        label={"Correo"}
                        bind:value={$form.email}
                        error={$form.errors?.email}
                    />
                    <!-- {#if $form.errors.email}
            <div class="text-black bg-opaemailty-30 bg-red pt-1">

                <span >{$form.errors.email}</span>
            </div>
            {/if} -->

                    <Input
                        labelClass="text-black"
                        type="password"
                        required={true}
                        name="password"
                        label={"Contraseña"}
                        bind:value={$form.password}
                    />
                </div>
                <!-- <button type="submit">Iniemailar sesión</button> -->

                <AnimatedButton
                    type="submit"
                    disabled={$form.processing}
                    value={$form.processing ? "Cargando..." : "E N T R A R"}
                />

                <div class="flex items-center justify-between gap-3 my-4 text-black/40">
                    <span class="h-px flex-1 bg-black/10"></span>
                    <span class="text-xs uppercase tracking-wide">o</span>
                    <span class="h-px flex-1 bg-black/10"></span>
                </div>

                <a
                    href="/login/google"
                    class="w-full flex items-center justify-center gap-3 bg-white text-slate-700 font-medium py-2.5 px-4 rounded-xl hover:bg-gray-100 transition shadow"
                >
                    <svg viewBox="0 0 24 24" class="w-5 h-5" aria-hidden="true">
                        <path
                            fill="#4285F4"
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"
                        />
                        <path
                            fill="#34A853"
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"
                        />
                        <path
                            fill="#FBBC05"
                            d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84Z"
                        />
                        <path
                            fill="#EA4335"
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A11 11 0 0 0 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52Z"
                        />
                    </svg>
                    Continuar con Google
                </a>

                <div class="mt-4 text-center">
                    <a
                        href="/olvidar-contrasena"
                        class="text-sm text-black hover:text-color2"
                    >
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    * {
        box-sizing: border-box;
    }
    input[type="submit"] {
        background-color: #490a75;
        color: white;
    }
</style>
