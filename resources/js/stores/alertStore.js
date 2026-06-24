import { writable } from "svelte/store";

export const alertInfo = writable({
    isOpen: false,
    type: "info",
    title: "",
    message: "",
})

export function displayAlert(objParams) {
    alertInfo.update((current) => {
        return {...objParams, isOpen: true}
    })

    let id = setTimeout(() => {
        alertInfo.set({
            isOpen: false,
            type: "info",
            title: "",
            message: "",
        })
    }, 5000);
    

    return () => {
        clearTimeout(id);
      };

}