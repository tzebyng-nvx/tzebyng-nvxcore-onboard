import { ref } from "vue";

/**
 * The start function for the tour registered by the page currently on screen.
 * Shells read this to render a "Take a tour" button that replays the active
 * page's tour. `null` means the current page has no tour.
 */
export const activeTour = ref<null | (() => void)>(null);
