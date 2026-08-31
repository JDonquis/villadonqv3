<script>
    // import { page } from "$app/stores";

    // $: url = $page.route.id
    // console.log(url)
    import { toggleMenu } from "../stores/navStatus.js";
    import { inertia, page } from "@inertiajs/svelte";

    const adminNavPages = [
        {
            icon: "uil:setting",
            href: "/dashboard/configuracion",
            name: "Configuración",
        },
        {
            icon: "mdi:school",
            href: "/dashboard/matricula",
            name: "Matricula",
        },

        {
            icon: "streamline:payment-10-solid",
            href: "/dashboard/pagos",
            name: "Pagos",
        },
        {
            icon: "mdi:finance",
            href: "/dashboard/estados-de-cuenta",
            name: "Estados de Cuenta",
        },
        {
            icon: "ph:users",
            href: "/dashboard/personal",
            name: "Personal",
        },
        {
            icon: "mdi:account-tie",
            href: "/dashboard/profesores",
            name: "Profesores",
        },
        {
            icon: "mdi:book-open-variant",
            href: "/dashboard/materias",
            name: "Materias",
        },
        {
            icon: "mdi:clipboard-check-outline",
            href: "/dashboard/planes-evaluacion",
            name: "Planes de Evaluación",
        },
        {
            icon: "mdi:calendar-clock",
            href: "/dashboard/horarios",
            name: "Horarios",
        },
    ];

    const teacherNavPages = [
        {
            icon: "mdi:clipboard-check-outline",

            href: "/dashboard/mis-planes",
            name: "Planes de Evaluación",
        },
        {
            icon: "mdi:account-group",
            href: "/dashboard/mis-estudiantes",
            name: "Mis Estudiantes",
        },
        {
            icon: "mdi:calendar-clock",
            href: "/dashboard/mi-horario",
            name: "Mi Horario",
        },
        {
            icon: "mdi:account",
            href: "/dashboard/perfil",
            name: "Mi Perfil",
        },
    ];

    const repNavPages = [
        {
            icon: "mdi:school",
            href: "/dashboard/mis-hijos",
            name: "Mis Hijos",
        },
        {
            icon: "streamline:payment-10-solid",
            href: "/dashboard/mis-pagos",
            name: "Mis Pagos",
        },
    ];

    $: isTeacher = Number($page.props.auth?.type_user_id) === 3;
    $: isRep = Number($page.props.auth?.type_user_id) === 2;
    $: hasRepStudents = $page.props.auth?.has_rep_students === true;

    $: navPages = (() => {
        if (isTeacher) {
            return teacherNavPages;
        }

        const base = isRep
            ? repNavPages
            : hasRepStudents
              ? [...adminNavPages, ...repNavPages]
              : adminNavPages;

        const seen = new Set();

        return base.filter((page) =>
            seen.has(page.href) ? false : (seen.add(page.href), true),
        );
    })();
</script>

<nav
    class="left_nav flex md:block items-center bg-color1 text-gray-100 h-full relative overflow-hidden"
>
    <button
        on:click={() => toggleMenu()}
        class="hidden md:block burger_icon items-center pt-1 text-center text-2xl text-gray-300 hover:text-yellow bg-gray-700 bg-opacity-50"
    >
        <iconify-icon class="mb-0 pb-0" icon="majesticons:menu-expand-left-line"
        ></iconify-icon>
    </button>
    <a
        use:inertia
        href="/dashboard"
        class=" text-lg md:block p-4 text-center w-full flex"
    >
        <img
            src="/img/Isotipo-villadonq-blanco.png"
            alt="Villadonq"
            class="w-10 h-10 object-contain inline"
        />
        <span class="logo hidden md:inline-block"> VILLADONQ </span>
    </a>
    <ul
        class="flex justify-around items-center w-full md:flex-col
    md:items-start md:justify-normal md:p-2 md:gap-1 md:[&>*]:w-full"
    >
        {#each navPages as navPage}
            <li>
                <a
                    href={navPage.href}
                    use:inertia
                    class="hover:text-yellow z-10 rounded-md flex md:gap-2 items-center p-2"
                    class:active={$page.url.startsWith(navPage.href)}
                    ><iconify-icon class="text-xl" icon={navPage.icon} /><span
                        class="label_link hidden md:block">{navPage.name}</span
                    ></a
                >
            </li>
        {/each}
    </ul>
</nav>

<style>
    .active {
        /* background: linear-gradient(80deg, #acd7f2 9%, transparent 99%); */
        position: relative;
        border-radius: 6px;
        /* box-shadow: 0 0 10px 0px #9BF2EA; */
        color: #ffd23f !important;
        font-weight: bold;
    }
    a:before {
    }
    a.active:before {
    }
    a:active {
    }
</style>
