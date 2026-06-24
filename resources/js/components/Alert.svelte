<script>
    import { alertInfo } from "../stores/alertStore";
    const clasesByTypeAlert = {
        success: "bg-green text-black",
        info: "bg-white text-black",
        error: "bg-red text-black",
    };
</script>

<div
    class={`fixed top-4 left-1/2 transform -translate-x-1/2 duration-150 border-3 border-black font-bold ${
        clasesByTypeAlert[$alertInfo.type]
    } ${$alertInfo.isOpen ? "opacity-100 px-4 py-2 translate-y-0" : "opacity-0 pointer-events-none translate-y-[-100%]"}`}
    style="z-index: 100000;"
>
    <b>{$alertInfo.message}</b>
</div>
