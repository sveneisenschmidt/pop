import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { init } from "../pop";
import type { PopConfig } from "../types";

describe("pop", () => {
    let container: HTMLElement;
    const originalLocation = window.location;

    beforeEach(() => {
        container = document.createElement("div");
        container.id = "pop-container";
        document.body.appendChild(container);
        vi.restoreAllMocks();

        // Mock window.location
        Object.defineProperty(window, "location", {
            value: { href: "https://example.com/test-page" },
            writable: true,
        });
    });

    afterEach(() => {
        document.body.removeChild(container);
        Object.defineProperty(window, "location", {
            value: originalLocation,
            writable: true,
        });
    });

    describe("onLoad callback", () => {
        it("calls onLoad when reactions are fetched", async () => {
            const mockReactionsResponse = {
                pageId: "test-page",
                reactions: { "👋": 5, "🔥": 3 },
                userReactions: ["👋"],
            };

            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve(mockReactionsResponse),
            });

            const onLoad = vi.fn();
            const config: PopConfig = {
                endpoint: "https://api.example.com",
                pageId: "test-page",
                el: "#pop-container",
                emojis: ["👋", "🔥"],
                renderReactions: true,
                onLoad,
            };

            await init(config);

            expect(onLoad).toHaveBeenCalledTimes(1);
        });

        it("calls onLoad when tracking visits", async () => {
            const mockVisitResponse = {
                success: true,
                recorded: true,
                uniqueVisitors: 42,
            };

            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve(mockVisitResponse),
            });

            const onLoad = vi.fn();
            const config: PopConfig = {
                endpoint: "https://api.example.com",
                pageId: "test-page",
                trackVisits: true,
                onLoad,
            };

            await init(config);

            expect(onLoad).toHaveBeenCalledTimes(1);
        });

        it("calls onLoad when no features enabled", async () => {
            const onLoad = vi.fn();
            const config: PopConfig = {
                endpoint: "https://api.example.com",
                pageId: "custom-page-id",
                onLoad,
            };

            await init(config);

            expect(onLoad).toHaveBeenCalledTimes(1);
        });

        it("does not call onLoad when not provided", async () => {
            const config: PopConfig = {
                endpoint: "https://api.example.com",
                pageId: "test-page",
            };

            // Should not throw
            await expect(init(config)).resolves.toBeUndefined();
        });

        it("calls onLoad even on fetch error", async () => {
            global.fetch = vi
                .fn()
                .mockRejectedValue(new Error("Network error"));
            const consoleSpy = vi
                .spyOn(console, "error")
                .mockImplementation(() => {});

            const onLoad = vi.fn();
            const config: PopConfig = {
                endpoint: "https://api.example.com",
                pageId: "test-page",
                el: "#pop-container",
                emojis: ["👋"],
                renderReactions: true,
                onLoad,
            };

            await init(config);

            expect(onLoad).toHaveBeenCalledTimes(1);

            consoleSpy.mockRestore();
        });
    });

    describe("init validation", () => {
        it("does not call onLoad when renderReactions is true but emojis is empty", async () => {
            const consoleSpy = vi
                .spyOn(console, "error")
                .mockImplementation(() => {});
            const onLoad = vi.fn();

            const config: PopConfig = {
                endpoint: "https://api.example.com",
                el: "#pop-container",
                emojis: [],
                renderReactions: true,
                onLoad,
            };

            await init(config);

            expect(onLoad).not.toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith(
                "Pop: renderReactions requires emojis to be defined",
            );

            consoleSpy.mockRestore();
        });

        it("does not call onLoad when el is missing but required", async () => {
            const consoleSpy = vi
                .spyOn(console, "error")
                .mockImplementation(() => {});
            const onLoad = vi.fn();

            const config: PopConfig = {
                endpoint: "https://api.example.com",
                emojis: ["👋"],
                renderReactions: true,
                onLoad,
            };

            await init(config);

            expect(onLoad).not.toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith(
                "Pop: el is required when renderVisits or renderReactions is enabled",
            );

            consoleSpy.mockRestore();
        });

        it("does not call onLoad when element is not found", async () => {
            const consoleSpy = vi
                .spyOn(console, "error")
                .mockImplementation(() => {});
            const onLoad = vi.fn();

            const config: PopConfig = {
                endpoint: "https://api.example.com",
                el: "#nonexistent",
                emojis: ["👋"],
                renderReactions: true,
                onLoad,
            };

            await init(config);

            expect(onLoad).not.toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith(
                'Pop: Element "#nonexistent" not found',
            );

            consoleSpy.mockRestore();
        });
    });
});
