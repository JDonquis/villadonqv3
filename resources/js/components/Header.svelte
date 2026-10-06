<script>
    // import { authHandlers } from "../../stores/authStore";
    import { inertia, page, router } from "@inertiajs/svelte";

    let pageName = "";
    $: userNav = false;
    $: showBackBtn = $page.url !== "/dashboard";

    const pageTitles = {
        Dashboard: "Inicio",
        "Dashboard/Matricula": "Matrícula",
        "Dashboard/Personal": "Personal",
        "Dashboard/Pagos": "Pagos",
        "Dashboard/EstadosDeCuenta": "Estados de Cuenta",
        "Dashboard/Configuracion": "Configuración",
        "Dashboard/MisHijos": "Mis Hijos",
        "Dashboard/MisPagos": "Mis Pagos",
        "Dashboard/Inicio": "Inicio",
        "Dashboard/Comunicados": "Comunicados",
        "Dashboard/Perfil": "Mi Perfil",
        "Dashboard/MiHorario": "Mi Horario",
        "Dashboard/HorarioHijo": "Horario del Estudiante",
        "Dashboard/MetodosDePago/Crear": "Nuevo Método de Pago",
        "Dashboard/MetodosDePago/Editar": "Editar Método de Pago",
    };

    $: pageTitle =
        pageTitles[$page.component] ||
        $page.component
            .split("/")
            .pop()
            .replace(/([A-Z])/g, " $1")
            .trim()
            .toUpperCase();

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
     $: authUser = $page.props.auth || {}; $: userInitials = `${(authUser.name || 'U').charAt(0)}${(authUser.last_name || '').charAt(0)}`.toUpperCase();
</script>



<header
    class="w-full bg-white/95 backdrop-blur-md border-b border-grayBlue/30  z-40 transition-shadow"
>
    <nav
        class="max-w-[1600px] mx-auto flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8 gap-4"
    >
        <!-- ============================================== -->
        <!-- LADO IZQUIERDO: RETORNO + TÍTULO DE PÁGINA -->
        <!-- ============================================== -->
        <div class="flex items-center gap-3 min-w-0">
            {#if showBackBtn}
                <button
                    type="button"
                    on:click={goBack}
                    class="w-9 h-9 rounded-xl border border-grayBlue/40 bg-slate-50/80 hover:bg-gray-100 text-color1 hover:text-color2 flex items-center justify-center transition-all duration-150 shadow-2xs shrink-0"
                    title="Volver"
                    aria-label="Volver a la página anterior"
                >
                    <iconify-icon
                        icon="mingcute:left-line"
                        class="text-xl leading-none"
                    ></iconify-icon>
                </button>
            {/if}
            <div class="flex items-center gap-2 truncate">
                <a
                    href="/dashboard"
                    use:inertia
                    class="text-sm md:text-base font-extrabold text-color1 hover:text-color2 transition-colors truncate tracking-tight flex items-center gap-2"
                >
                    <span
                        class="w-2 h-2 rounded-full bg-color2 hidden sm:inline-block"
                    ></span> <span>{pageTitle}</span>
                </a>
            </div>
        </div>
        <!-- ============================================== -->
        <!-- LADO DERECHO: PERFIL DE USUARIO Y MENÚ DROPDOWN-->
        <!-- ============================================== -->
        <div
            class="relative"
            use:clickOutside={() => {
                userNav = false;
            }}
        >
            <!-- BOTÓN TRIGGER UNIFICADO -->
            <button
                type="button"
                on:click={toggleNavUser}
                class="flex items-center gap-3 p-1.5 pl-2.5 rounded-2xl hover:bg-slate-50 border border-transparent hover:border-grayBlue/30 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-color2/20 select-none {userNav
                    ? 'bg-slate-50 border-grayBlue/40 shadow-xs'
                    : ''}"
                aria-expanded={userNav}
                aria-haspopup="true"
            >
                <!-- Información de texto del usuario (oculta en móvil para ahorrar espacio) -->
                <div
                    class="hidden md:flex flex-col text-right leading-tight min-w-0"
                >
                    <span
                        class="text-xs font-bold text-color1 truncate max-w-[160px]"
                    >
                        {authUser.name || "Usuario"}
                        {authUser.last_name || ""}
                    </span>
                    <span
                        class="text-[11px] font-medium text-gray-400 truncate max-w-[160px]"
                    >
                        {authUser.role_name ||
                            authUser.email ||
                            "Control de Estudios"}
                    </span>
                </div>
                <!-- Avatar con Anillo y Estado -->
                <div class="relative shrink-0">
                    <div
                        class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-color2/70 hover:bg-color1git text-white font-bold text-xs flex items-center justify-center overflow-hidden shadow-2xs ring-2 ring-color2/20"
                    >
                        {#if authUser.photo && authUser.photo !== "guest.webp"}
                            <img
                                src={`/img/photos/${authUser.photo}`}
                                alt="Foto de perfil"
                                class="w-full h-full object-cover"
                            />
                        {:else if userInitials.trim()}
                            <span class="tracking-wider">{userInitials}</span>
                        {:else}
                            <iconify-icon
                                icon="solar:user-broken"
                                class="text-xl"
                            ></iconify-icon>
                        {/if}
                    </div>
                    <!-- Punto de conexión / estado en línea -->
                    <span
                        class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"
                    ></span>
                </div>
                <!-- Flechita chevron animada -->
                <iconify-icon
                    icon="solar:alt-arrow-down-broken"
                    class="text-gray-400 text-sm transition-transform duration-200 {userNav
                        ? 'rotate-180 text-color2'
                        : ''}"
                ></iconify-icon>
            </button>
            <!-- ============================================== -->
            <!-- MENÚ DROPDOWN FLOTANTE (ALINEADO A LA DERECHA) -->
            <!-- ============================================== -->
            {#if userNav}
                <div
                    class="absolute right-0 top-full mt-2 w-60 rounded-2xl bg-white border border-grayBlue/40 shadow-2xl p-1.5 z-50 text-gray-700 animate-in fade-in zoom-in-95 duration-150"
                    role="menu"
                >
                    <!-- Header interno del dropdown (útil especialmente en móvil) -->
                    <div class="px-3.5 py-2.5 border-b border-grayBlue/20 mb-1">
                        <p class="text-xs font-bold text-color1 truncate">
                            {authUser.name}
                            {authUser.last_name}
                        </p>
                        <p
                            class="text-[11px] text-gray-400 truncate mt-0.5 font-mono"
                        >
                            {authUser.email || "sesion@activa.edu"}
                        </p>
                    </div>
                    <!-- Enlace: Mi Perfil -->
                    <a
                        href="/dashboard/perfil"
                        use:inertia
                        on:click={() => (userNav = false)}
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-color1 hover:text-color2 hover:bg-slate-50 transition-colors"
                        role="menuitem"
                    >
                        <span
                            class="w-7 h-7 rounded-lg bg-color4/20 text-color2 flex items-center justify-center"
                        >
                            <iconify-icon
                                icon="solar:user-circle-bold-duotone"
                                class="text-base"
                            ></iconify-icon>
                        </span> <span>Mi Perfil</span>
                    </a>
                    <!-- Enlace / Ajustes (Opcional, si tienes) -->
                    <a
                        href="/dashboard/configuracion"
                        use:inertia
                        on:click={() => (userNav = false)}
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-color1 hover:text-color2 hover:bg-slate-50 transition-colors"
                        role="menuitem"
                    >
                        <span
                            class="w-7 h-7 rounded-lg bg-slate-100 text-gray-500 flex items-center justify-center"
                        >
                            <iconify-icon
                                icon="solar:settings-bold-duotone"
                                class="text-base"
                            ></iconify-icon>
                        </span> <span>Configuración</span>
                    </a>
                    <div class="h-px bg-grayBlue/20 my-1"></div>
                    <!-- Botón / Enlace: Cerrar Sesión -->
                    <a
                        href="/logout"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-red hover:bg-red/10 transition-colors w-full"
                        role="menuitem"
                    >
                        <span
                            class="w-7 h-7 rounded-lg bg-red/10 text-red flex items-center justify-center"
                        >
                            <iconify-icon
                                icon="solar:logout-2-bold-duotone"
                                class="text-base"
                            ></iconify-icon>
                        </span> <span>Cerrar sesión</span>
                    </a>
                </div>
            {/if}
        </div>
    </nav>
</header>
