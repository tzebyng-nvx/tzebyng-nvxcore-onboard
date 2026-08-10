import { driver, type DriveStep } from "driver.js";
import "driver.js/dist/driver.css";
import { onMounted, onUnmounted } from "vue";
import { activeTour } from "./tourController";

interface TourOptions {
    /** Auto-run on the first visit to this tour. Defaults to true. */
    auto?: boolean;
    /** Milliseconds to wait before an auto-run so async page content can mount. */
    autoDelay?: number;
}

/**
 * Wire a guided product tour to the current page.
 *
 * - Auto-runs once on the user's first visit (tracked in localStorage under
 *   `tour_seen_{key}`), unless `auto` is false.
 * - Registers its `start` function as the active tour so a shell-level
 *   "Take a tour" button can replay it on demand.
 *
 * Steps whose target element is missing at run time are skipped gracefully by
 * driver.js, so pages with async data still tour cleanly.
 */
export function useTour(key: string, steps: DriveStep[], options: TourOptions = {}) {
    const { auto = true, autoDelay = 600 } = options;
    const storageKey = `tour_seen_${key}`;

    function start() {
        driver({
            showProgress: true,
            allowClose: true,
            nextBtnText: "Next",
            prevBtnText: "Back",
            doneBtnText: "Done",
            steps: steps.filter((step) => {
                const el = step.element;
                if (!el || typeof el !== "string") {
                    return true;
                }
                return document.querySelector(el) !== null;
            }),
        }).drive();
    }

    onMounted(() => {
        activeTour.value = start;

        if (auto && !localStorage.getItem(storageKey)) {
            localStorage.setItem(storageKey, "1");
            window.setTimeout(start, autoDelay);
        }
    });

    onUnmounted(() => {
        if (activeTour.value === start) {
            activeTour.value = null;
        }
    });

    return { start };
}
