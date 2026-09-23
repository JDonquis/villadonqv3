<script>
    import { onMount, onDestroy } from "svelte";

    export let latitude = null;
    export let longitude = null;
    export let readonly = false;
    export let onSelect = () => {};

    let mapContainer;
    let map = null;
    let marker = null;
    let googleMapsLoaded = false;

    const DEFAULT_CENTER = { lat: 10.4806, lng: -66.9036 };
    const API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

    async function loadGoogleMaps() {
        if (googleMapsLoaded || window.google?.maps) {
            googleMapsLoaded = true;
            return;
        }

        if (!API_KEY) {
            console.warn("Google Maps API key not configured (VITE_GOOGLE_MAPS_API_KEY)");
            return;
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = `https://maps.googleapis.com/maps/api/js?key=${API_KEY}&libraries=places`;
            script.async = true;
            script.defer = true;
            script.onload = () => {
                googleMapsLoaded = true;
                resolve();
            };
            script.onerror = () => {
                console.error("Failed to load Google Maps API");
                reject(new Error("Failed to load Google Maps API"));
            };
            document.head.appendChild(script);
        });
    }

    function initMap() {
        if (!mapContainer || !window.google?.maps) return;

        const center = latitude && longitude
            ? { lat: parseFloat(latitude), lng: parseFloat(longitude) }
            : DEFAULT_CENTER;

        map = new google.maps.Map(mapContainer, {
            center,
            zoom: 15,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
            styles: [
                {
                    featureType: "poi",
                    elementType: "labels",
                    stylers: [{ visibility: "off" }],
                },
            ],
        });

        marker = new google.maps.Marker({
            position: center,
            map,
            draggable: !readonly,
            title: "Ubicación del plantel",
        });

        if (!readonly) {
            map.addListener("click", (e) => {
                setMarkerPosition(e.latLng);
            });

            marker.addListener("dragend", () => {
                const pos = marker.getPosition();
                setMarkerPosition(pos);
            });
        }

        if (latitude && longitude) {
            map.setCenter(center);
        }
    }

    function setMarkerPosition(latLng) {
        if (!marker || !map) return;

        marker.setPosition(latLng);
        map.panTo(latLng);

        const lat = latLng.lat();
        const lng = latLng.lng();

        latitude = lat.toFixed(8);
        longitude = lng.toFixed(8);

        onSelect(latitude, longitude);
    }

    function useCurrentLocation() {
        if (!navigator.geolocation) {
            alert("Geolocalización no soportada en este navegador");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                setMarkerPosition(new google.maps.LatLng(pos.lat, pos.lng));
                map?.setZoom(16);
            },
            (error) => {
                alert("No se pudo obtener la ubicación: " + error.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    onMount(async () => {
        await loadGoogleMaps();
        if (googleMapsLoaded) {
            initMap();
        }
    });

    onDestroy(() => {
        if (marker) marker.setMap(null);
        if (map) google.maps.event.clearInstanceListeners(map);
    });
</script>

<div class="relative bg-gray-100 rounded-lg overflow-hidden" style="height: 300px;">
    <div bind:this={mapContainer} class="w-full h-full"></div>

    {#if !googleMapsLoaded && !API_KEY}
        <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50 text-gray-500 p-4">
            <iconify-icon icon="mdi:map-marker-off" class="text-4xl mb-2"></iconify-icon>
            <p class="text-center">
                Google Maps no configurado.<br>
                Agrega <code>VITE_GOOGLE_MAPS_API_KEY</code> en <code>.env</code>
            </p>
        </div>
    {:else if !googleMapsLoaded}
        <div class="absolute inset-0 flex items-center justify-center bg-gray-50">
            <div class="animate-spin rounded-full h-8 w-8 border-3 border-color1 border-t-transparent"></div>
        </div>
    {/if}

    {#if !readonly && googleMapsLoaded}
        <div class="absolute bottom-3 left-3 right-3 md:left-auto md:w-auto md:right-3 md:bottom-3">
            <button
                type="button"
                on:click={useCurrentLocation}
                class="bg-white shadow-md rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2"
                title="Usar mi ubicación actual"
            >
                <iconify-icon icon="mdi:crosshairs-gps" class="text-color1"></iconify-icon>
                Usar mi ubicación
            </button>
        </div>
    {/if}
</div>