<!-- FloatingInput.svelte -->
<script>
    export let value = "";
    export let label = "";
    export let required = "";
    export let placeholder = "";
    export let type = "text";
    export let classes = "";
    export let labelClass = "";
    export let theme = "ligtht";
    export let min = "";
    export let readonly = false;

    export let max = "";
    export let name = "";
    export let style = "";
    export let error = false;
    export let disabled = false;
</script>

<div class={`text-left w-full mt-5 ${classes} `} {style}>
    <label
        for={"nombre"}
        class={`form__label w-full text-sm font-semibold text-gray-700 ${labelClass}`}
        {placeholder}>{label} {required ? "*" : ""}</label
    >
    <div class="relative w-full parent_div">
        {#if type === "textarea"}
            <textarea
                bind:value
                id={label}
                rows="1"
                class="form__field "
                on:change
                {readonly}
                on:input
            ></textarea>
        {:else if type === "select"}
            <select
                id={label}
                bind:value
                {required}
                class="form__field "
                on:change
                on:input
                disabled={readonly || disabled}
            >
                <slot></slot>
            </select>
        {:else}
            <input
                bind:value
                {...{ type }}
                id={label}
                {name}
                class="form__field "
                {required}
                {max}
                {min}
                {readonly}
                on:change
                on:input
            />
        {/if}
        {#if error}
            <div class="text-black font-semibold bg-red pt-1 px-2">
                <span>{error}</span>
            </div>
        {/if}
    </div>
</div>

<!-- <input  class="bg-color1 block px-2.5 pb-2.5 pt-4 w-full text-sm   rounded-lg  appearance-none dark:text-white  focus:outline-none focus:ring-0  peer " placeholder=" "   />
    <label  class="absolute text-sm   duration-300 transform -translate-y-4 scale-75 top-4 z-10 origin-[0] rounded-xl px-2 peer-focus:px-2 text-white peer-focus:text-color2 peer-focus:dark:text-color2  peer-placeholder-shown:text-color2 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-4 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1 ">{label}</label> -->

<style>
    input,
    textarea,
    select {
        width: 100%;
        padding: 10px 14px;
        border-radius: 6px;
        font-family: inherit;
        border: 1px solid #ccc;
    }
    select {
        padding: 11px;
    }

    input:focus,
    textarea:focus,
    select:focus {
        outline: none;
    }
    input:-webkit-autofill,
    textarea:-webkit-autofill,
    select:-webkit-autofill {
        -webkit-box-shadow: 0 0 0 50px #3b414d inset; /* Change the color to your own background color */
        -webkit-text-fill-color: gainsboro;
        font-family: "Figtree";
    }

    input:-webkit-autofill:focus,
    textarea:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0 50px #3b414d inset; /*your box-shadow*/
        -webkit-text-fill-color: gainsboro;
        font-family: "Figtree";
    }
    .parent_div:before {
        content: "";
        display: block;
        position: absolute;
        transition: 0.15s cubic-bezier(0.39, 0.575, 0.565, 1);
        bottom: 0px;
        left: 2.5px;
        border-radius: 4px;
        height: 2px;
        background: linear-gradient(80deg, #54ffaf 9%, #1f4287 93%);
        background-color: aqua;
        width: 0%;
    }
    .parent_div:focus-within::before {
        width: 98%;
    }
    .nb-input {
        padding: 0.5rem 1rem;
        border: 3px solid #000;
        border-radius: 0;
        background: #ffffffd8;
        box-shadow: 3px 3px 0 0 #000;
        transition:
            box-shadow 0.15s,
            transform 0.15s;
    }
    select.nb-input {
        padding: 10.5px;
    }

    .nb-input:focus {
        outline: 3px solid #74b9ff;
        outline-offset: 2px;
        box-shadow: 5px 5px 0 0 #000;
        transform: translate(-1px, -1px);
    }
</style>
