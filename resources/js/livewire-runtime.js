// O ESM do Livewire escreve em window durante o import, antes de start().
// A proteção precisa ocorrer antes desse import, inclusive após wire:navigate.
export function bootLivewire({ window, asset, load, initialize }) {
    const key = '__escalaFacilRuntime';
    const previous = window[key];
    if (previous?.asset === asset) return previous.promise;

    if (previous || window.Livewire || window.Alpine) {
        // Uma nova versão do bundle não pode operar sobre componentes da antiga.
        // A navegação já atualizou a URL: a recarga mantém o destino e seus filtros.
        if (!window.__escalaFacilRuntimeReloading) {
            window.__escalaFacilRuntimeReloading = true;
            window.location.reload();
        }
        return Promise.resolve(false);
    }

    const state = { asset, promise: null };
    window[key] = state;
    state.promise = Promise.resolve().then(load).then(initialize).then(() => true);
    return state.promise;
}
