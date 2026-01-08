import { describe, it, expect, vi, beforeEach } from "vitest";
import {
    renderButtons,
    updateButton,
    renderVisitorCount,
    updateVisitorCount,
} from "../render";

describe("render", () => {
    let container: HTMLElement;

    beforeEach(() => {
        container = document.createElement("div");
    });

    describe("renderButtons", () => {
        it("renders buttons for all emojis", () => {
            const onClick = vi.fn();
            renderButtons(container, ["👋", "🔥", "❤️"], {}, [], onClick);

            const buttons = container.querySelectorAll(".pop-btn");
            expect(buttons.length).toBe(3);
            expect(buttons[0].textContent).toContain("👋");
            expect(buttons[1].textContent).toContain("🔥");
            expect(buttons[2].textContent).toContain("❤️");
        });

        it("displays counts for emojis", () => {
            const onClick = vi.fn();
            renderButtons(
                container,
                ["👋", "🔥"],
                { "👋": 5, "🔥": 3 },
                [],
                onClick,
            );

            const buttons = container.querySelectorAll(".pop-btn");
            expect(buttons[0].querySelector("span")?.textContent).toBe("5");
            expect(buttons[1].querySelector("span")?.textContent).toBe("3");
        });

        it("shows 0 for emojis without counts", () => {
            const onClick = vi.fn();
            renderButtons(container, ["👋"], {}, [], onClick);

            const span = container.querySelector(".pop-btn span");
            expect(span?.textContent).toBe("0");
        });

        it("marks user reactions as active", () => {
            const onClick = vi.fn();
            renderButtons(container, ["👋", "🔥"], {}, ["👋"], onClick);

            const buttons = container.querySelectorAll(".pop-btn");
            expect(buttons[0].classList.contains("pop-btn--active")).toBe(true);
            expect(buttons[1].classList.contains("pop-btn--active")).toBe(
                false,
            );
        });

        it("calls onClick when button is clicked", () => {
            const onClick = vi.fn();
            renderButtons(container, ["👋"], {}, [], onClick);

            const button = container.querySelector(
                ".pop-btn",
            ) as HTMLButtonElement;
            button.click();

            expect(onClick).toHaveBeenCalledWith("👋", button);
        });

        it("calls onClick for active buttons (toggle off)", () => {
            const onClick = vi.fn();
            renderButtons(container, ["👋"], {}, ["👋"], onClick);

            const button = container.querySelector(
                ".pop-btn",
            ) as HTMLButtonElement;
            button.click();

            expect(onClick).toHaveBeenCalledWith("👋", button);
        });
    });

    describe("updateButton", () => {
        it("updates count and sets active state", () => {
            const button = document.createElement("button");
            button.innerHTML = "👋 <span>5</span>";

            updateButton(button, 6, true);

            expect(button.querySelector("span")?.textContent).toBe("6");
            expect(button.classList.contains("pop-btn--active")).toBe(true);
        });

        it("updates count and removes active state", () => {
            const button = document.createElement("button");
            button.classList.add("pop-btn--active");
            button.innerHTML = "👋 <span>5</span>";

            updateButton(button, 4, false);

            expect(button.querySelector("span")?.textContent).toBe("4");
            expect(button.classList.contains("pop-btn--active")).toBe(false);
        });
    });

    describe("renderVisitorCount", () => {
        it("creates visitor element with count", () => {
            renderVisitorCount(container, 5);

            const visitorEl = container.querySelector(".pop-visitors");
            expect(visitorEl).not.toBeNull();
            expect(visitorEl?.textContent).toBe("👀 5");
        });

        it("shows count for 1 visitor", () => {
            renderVisitorCount(container, 1);

            const visitorEl = container.querySelector(".pop-visitors");
            expect(visitorEl?.textContent).toBe("👀 1");
        });

        it("shows 0 visitors", () => {
            renderVisitorCount(container, 0);

            const visitorEl = container.querySelector(".pop-visitors");
            expect(visitorEl?.textContent).toBe("👀 0");
        });

        it("reuses existing visitor element", () => {
            renderVisitorCount(container, 5);
            renderVisitorCount(container, 10);

            const visitorEls = container.querySelectorAll(".pop-visitors");
            expect(visitorEls.length).toBe(1);
            expect(visitorEls[0].textContent).toBe("👀 10");
        });
    });

    describe("updateVisitorCount", () => {
        it("updates existing visitor count", () => {
            renderVisitorCount(container, 5);
            updateVisitorCount(container, 10);

            const visitorEl = container.querySelector(".pop-visitors");
            expect(visitorEl?.textContent).toBe("👀 10");
        });

        it("handles count on update", () => {
            renderVisitorCount(container, 5);
            updateVisitorCount(container, 1);

            const visitorEl = container.querySelector(".pop-visitors");
            expect(visitorEl?.textContent).toBe("👀 1");
        });

        it("does nothing if visitor element does not exist", () => {
            updateVisitorCount(container, 10);

            const visitorEl = container.querySelector(".pop-visitors");
            expect(visitorEl).toBeNull();
        });
    });
});
