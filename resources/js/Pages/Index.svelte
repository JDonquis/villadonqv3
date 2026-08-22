<script>
    import { useForm } from "@inertiajs/svelte";

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
