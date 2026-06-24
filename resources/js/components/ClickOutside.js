export default function clickOutside(element, callbackFunction) {
    // console.log('click')
    function onClick(event) {
        if (!element.contains(event.target)) {
            callbackFunction();
        }
    }
    
    document.body.addEventListener('click', onClick);
    
    return {
        update(newCallbackFunction) {
            callbackFunction = newCallbackFunction;
        },
        destroy() {
            document.body.removeEventListener('click', onClick);
        }
    }
}