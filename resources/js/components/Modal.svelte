<script>
	import { onMount, onDestroy } from "svelte";

	export let showModal; // boolean
	export let classes = "";
	export let keyShortcut = null;
	export let onKeyShortcut = null;

	function handleKeydown(event) {
		if (event.key === "Escape" && showModal) {
			showModal = false;
			return;
		}
		if (!keyShortcut || showModal) return;
		if (event.key.toLowerCase() !== keyShortcut.toLowerCase()) return;
		if (event.altKey || event.ctrlKey || event.metaKey) return;
		const target = event.target;
		if (
			target &&
			(target.tagName === "INPUT" ||
				target.tagName === "TEXTAREA" ||
				target.tagName === "SELECT" ||
				target.isContentEditable)
		)
			return;
		event.preventDefault();
		if (onKeyShortcut) {
			onKeyShortcut();
		} else {
			showModal = true;
		}
	}

	onMount(() => {
		document.addEventListener("keydown", handleKeydown);
	});

	onDestroy(() => {
		document.removeEventListener("keydown", handleKeydown);
	});
</script>

<!-- svelte-ignore a11y-click-events-have-key-events a11y-no-noninteractive-element-interactions -->
<div
	class="fixed inset-0 z-[99999] flex items-center justify-center transition-opacity duration-100 {showModal ? 'bg-black bg-opacity-30 backdrop-blur-sm opacity-100' : 'opacity-0 pointer-events-none'}"
	on:click={showModal ? () => (showModal = false) : null}
	role="presentation"
>
	<!-- svelte-ignore a11y-no-static-element-interactions -->
	<div
		class="bg-white rounded-xl p-4 max-w-[98vw] max-h-[98vh] overflow-auto relative transition-transform duration-200 {showModal ? 'scale-100' : 'scale-95'} {classes}"
		on:click|stopPropagation
		role="dialog"
		aria-modal="true"
		tabindex="-1"
	>
		<slot name="header" />
		<button class="absolute right-4 top-4" on:click={() => (showModal = false)}>
			<iconify-icon icon="line-md:close" width="24" height="24"></iconify-icon>
		</button>
		<slot />
		<hr class="my-4" />
		<div class="flex justify-end gap-12">
			<slot name="btn_footer"></slot>
		</div>
	</div>
</div>

<style>
	button {
		display: block;
	}
	hr {
		opacity: 0.2;
	}
</style>
