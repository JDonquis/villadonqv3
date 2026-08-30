<script>
    // import { authHandlers } from "../../stores/authStore";
    import { inertia, page, router } from "@inertiajs/svelte";

let pageName = "";
    $: userNav = false;
    $: showBackBtn = $page.url !== "/dashboard";

    const pageTitles = {
        'Dashboard': 'Inicio',
        'Dashboard/Matricula': 'Matrícula',
        'Dashboard/Personal': 'Personal',
        'Dashboard/Pagos': 'Pagos',
        'Dashboard/EstadosDeCuenta': 'Estados de Cuenta',
        'Dashboard/Configuracion': 'Configuración',
        'Dashboard/MisHijos': 'Mis Hijos',
        'Dashboard/MisPagos': 'Mis Pagos',
        'Dashboard/Perfil': 'Mi Perfil',
        'Dashboard/MiHorario': 'Mi Horario',
        'Dashboard/HorarioHijo': 'Horario del Estudiante',
        'Dashboard/MetodosDePago/Crear': 'Nuevo Método de Pago',
        'Dashboard/MetodosDePago/Editar': 'Editar Método de Pago',
    };

    $: pageTitle = pageTitles[$page.component] || $page.component.split('/').pop().replace(/([A-Z])/g, ' $1').trim().toUpperCase();

    function toggleNavUser() {
        userNav = !userNav;
        console.log(userNav);
    }

    function goBack() {
        window.history.back();
    }

    function clickOutside(element, callbackFunction) {
        // console.log('click')
        function onClick(event) {
            if (!element.contains(event.target)) {
                callbackFunction();
            }
        }

        document.body.addEventListener("click", onClick);

        return {
            update(newCallbackFunction) {
                callbackFunction = newCallbackFunction;
            },
            destroy() {
                document.body.removeEventListener("click", onClick);
            },
        };
    }
</script>

<header class="  w-full">
    <nav
        class="flex justify-between items-center w-full max-h-32 py-2 gap-3 text-sm px-3 md:px-5"
    >
        <span class="flex gap-1 items-center">
            {#if showBackBtn}
                <button
                    on:click={goBack}
                    class="mr-2 p-1 hover:bg-gray-200 rounded"
                    title="Volver"
                >
                    <iconify-icon icon="mingcute:left-line" class="text-xl" />
                </button>
            {/if}

            <a
                href="/dashboard"
                use:inertia
                class="text-sm hidden md:inline font-bold"
                >{pageTitle}</a
            >
        </span>
        <!-- <div class="flex bg-color2  md:min-w-72 rounded-full items-center">
                <iconify-icon icon="cil:search" class="mx-2" />
                <input
                    type="search"
                    placeholder="Buscar"
                    name=""
                    id=""
                    class="bg-color2 px-3 py-2 rounded-full  w-full"
                />
            </div> -->

        <!-- svelte-ignore a11y-click-events-have-key-events -->
        <div
            class="cursor-pointer flex gap-2 text-right items-center relative"
            use:clickOutside={() => {
                userNav = false;
            }}
        >
            <div
                class="hidden md:block relative -top-1"
                on:click={toggleNavUser}
            >
                <b>
                    <iconify-icon
                        icon="solar:alt-arrow-down-broken"
                        class="text-xl relative top-1"
                    />
                    {$page.props.auth.name}
                    {$page.props.auth.last_name}
                </b>
                <!-- <p>juanvillans@gmail.com</p> -->
            </div>
            <div
                class="h-10 aspect-square rounded-full bg-green1 z-20 flex justify-center items-center text-green3 overflow-hidden"
                on:click={toggleNavUser}
                class:blueShadow={userNav}
            >
                {#if $page.props.auth.photo && $page.props.auth.photo !== "guest.webp"}
                    <img
                        src="/img/photos/{$page.props.auth.photo}"
                        alt="Foto de perfil"
                        class="w-full h-full object-cover"
                    />
                {:else}
                    <iconify-icon
                        icon="solar:user-broken"
                        class="text-2xl"
                    class:text-green4={userNav}
                />
                {/if}
            </div>
            {#if userNav}
                <div
                    class="absolute rounded-md flex items-center flex-col bg-color1 w-full z-50 px-3 top-10 -left-10 rounded-tr-none text-gray-100"
                >
                    <a
                        href="/dashboard/perfil"
                        use:inertia
                        class="p-2 cursor-pointer hover:underline hover:text-gray-50 inline-block"
                        >Mi Perfil</a
                    >
                    <a
                        href="/logout"
                        class="p-2 cursor-pointer hover:underline hover:text-gray-50 inline-block"
                        >Cerrar sesión</a
                    >
                    <!-- <button on:click={authHandlers.logout} class="p-4 flex items-center text-rigth w-full justify-end hover:text-green4 gap-2 hover:font-bold hover:underline" >Cerrar sesión <iconify-icon icon="solar:logout-line-duotone" class="text-xl"></iconify-icon></button> -->
                </div>
            {/if}
        </div>
    </nav>
</header>
