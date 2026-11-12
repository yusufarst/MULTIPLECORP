import { createContext, useContext } from 'react';

// DESIGN_SYSTEM §5's layer scale for a body-level popup — a popover (the business date's calendar, the company
// filter), a combobox's list, a select's or a menu's popup, a tooltip —: opened from the page it takes `--z-popover`,
// below every modal layer; opened from inside a modal layer (a dialog, an alert dialog, a sheet) it takes that layer's
// own `z` and, portaled after it, stacks above it, reachable and fully shown (TASK-091). The scale's tokens are unchanged.
export type PopupLayer = 'z-(--z-popover)' | 'z-(--z-sheet)' | 'z-(--z-modal)';

const LayerContext = createContext<PopupLayer>('z-(--z-popover)');

/** Given by a modal layer to everything it holds. */
export const PopupLayerProvider = LayerContext.Provider;

/** The layer class a popup's positioner (and its phone backdrop) takes where it is opened. */
export function usePopupLayer(): PopupLayer {
    return useContext(LayerContext);
}
