<script>
	export let showModal; // boolean
	export let classes = "";
</script>

<!-- svelte-ignore a11y-click-events-have-key-events a11y-no-noninteractive-element-interactions -->
<div
	class="fixed inset-0 z-[99999] flex items-center justify-center transition-opacity duration-100 {showModal ? 'bg-black bg-opacity-30 backdrop-blur-sm opacity-100' : 'opacity-0 pointer-events-none'}"
	on:click={showModal ? () => (showModal = false) : null}
>
	<!-- svelte-ignore a11y-no-static-element-interactions -->
	<div
		class="bg-white rounded-xl p-4 max-w-[98vw] max-h-[98vh] overflow-auto relative transition-transform duration-200 {showModal ? 'scale-100' : 'scale-95'} {classes}"
		on:click|stopPropagation
	>
		<slot name="header" />
		<button class="absolute right-4 top-4" on:click={() => (showModal = false)}>
			<iconify-icon icon="line-md:close" width="24" height="24"></iconify-icon>
		</button>
		<hr class="mt-3" />
		<slot />
		<hr class="my-4" />
		<div class="flex justify-end gap-12">
			<slot name="btn_footer"></slot>
		</div>
	</div>
</div>

<style>
	dialog {
		max-width: 98vw;
		border: 4px solid black;
		padding: 0;
	}
	dialog::backdrop {
		background: rgba(0, 0, 0, 0.3);
		backdrop-filter: blur(0.1px);
	}
	dialog > div {
		padding: 1em;
	}
	dialog[open] {
		animation: zoom 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
	}
	@keyframes zoom {
		from {
			transform: scale(0.95);
		}
		to {
			transform: scale(1);
		}
	}
	dialog[open]::backdrop {
		animation: fade 0.2s ease-out;
	}
	@keyframes fade {
		from {
			opacity: 0;
		}
		to {
			opacity: 1;
		}
	}
	button {
		display: block;
	}
    hr {
        opacity: .2;
    }
</style>
