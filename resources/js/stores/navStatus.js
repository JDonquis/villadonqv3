import { writable } from "svelte/store";

const storedNav = typeof localStorage !== 'undefined' ? localStorage.getItem('navStatus') : null;
const initialNav = storedNav ? JSON.parse(storedNav) : { isContracted: false, navWidth: 240 };

export const navStatus = writable(initialNav);

export function toggleMenu(objParams) {
    navStatus.update((current) => {
        let newStatus = !current.isContracted;
        const newNav = newStatus == true
            ? { isContracted: newStatus, navWidth: 60 }
            : { isContracted: newStatus, navWidth: 240 };

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('navStatus', JSON.stringify(newNav));
        }

        return newNav;
    });
}
