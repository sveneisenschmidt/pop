import { describe, it, expect, vi, beforeEach } from "vitest";
import { fetchReactions, toggleReaction, recordVisit } from "../src/api";

describe("api", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe("fetchReactions", () => {
    it("fetches reactions from endpoint", async () => {
      const mockResponse = {
        pageId: "https://example.com/page1",
        reactions: { "👋": 5, "🔥": 3 },
        userReactions: ["👋"],
      };

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await fetchReactions(
        "https://api.example.com",
        "https://example.com/page1",
      );

      expect(fetch).toHaveBeenCalledWith(
        "https://api.example.com/reactions?pageId=https%3A%2F%2Fexample.com%2Fpage1",
        { method: "GET", credentials: "omit" },
      );
      expect(result).toEqual(mockResponse);
    });

    it("throws on non-ok response", async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
      });

      await expect(
        fetchReactions("https://api.example.com", "https://example.com/page1"),
      ).rejects.toThrow("Failed to fetch reactions: 500");
    });
  });

  describe("toggleReaction", () => {
    it("toggles reaction and returns result", async () => {
      const mockResponse = { success: true, action: "added", count: 6 };

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await toggleReaction(
        "https://api.example.com",
        "https://example.com/page1",
        "👋",
      );

      expect(fetch).toHaveBeenCalledWith("https://api.example.com/reactions", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "omit",
        body: JSON.stringify({
          pageId: "https://example.com/page1",
          emoji: "👋",
        }),
      });
      expect(result).toEqual(mockResponse);
    });

    it("returns removed action when toggling off", async () => {
      const mockResponse = { success: true, action: "removed", count: 5 };

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await toggleReaction(
        "https://api.example.com",
        "https://example.com/page1",
        "👋",
      );

      expect(result.action).toBe("removed");
      expect(result.count).toBe(5);
    });

    it("throws on non-ok response", async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 429,
      });

      await expect(
        toggleReaction(
          "https://api.example.com",
          "https://example.com/page1",
          "👋",
        ),
      ).rejects.toThrow("Failed to toggle reaction: 429");
    });
  });

  describe("recordVisit", () => {
    it("records visit and returns result", async () => {
      const mockResponse = { success: true, recorded: true, uniqueVisitors: 5 };

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await recordVisit(
        "https://api.example.com",
        "https://example.com/page1",
      );

      expect(fetch).toHaveBeenCalledWith("https://api.example.com/visits", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "omit",
        body: JSON.stringify({
          pageId: "https://example.com/page1",
        }),
      });
      expect(result).toEqual(mockResponse);
    });

    it("returns recorded false when deduplicated", async () => {
      const mockResponse = {
        success: true,
        recorded: false,
        uniqueVisitors: 5,
      };

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await recordVisit(
        "https://api.example.com",
        "https://example.com/page1",
      );

      expect(result.recorded).toBe(false);
      expect(result.uniqueVisitors).toBe(5);
    });

    it("throws on non-ok response", async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 429,
      });

      await expect(
        recordVisit("https://api.example.com", "https://example.com/page1"),
      ).rejects.toThrow("Failed to record visit: 429");
    });
  });
});
