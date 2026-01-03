/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

import type { PopConfig } from "./types";
import { fetchReactions, toggleReaction, recordVisit } from "./api";
import {
  renderButtons,
  updateButton,
  renderVisitorCount,
  updateVisitorCount,
} from "./render";

export async function init(config: PopConfig): Promise<void> {
  const pageId = config.pageId || window.location.href;
  const emojis = config.emojis || [];

  // Silent mode: only record visit, no UI
  if (config.silent) {
    try {
      await recordVisit(config.endpoint, pageId);
    } catch (error) {
      console.error("Pop: Failed to record visit", error);
    }
    return;
  }

  const container = document.querySelector<HTMLElement>(config.el);
  if (!container) {
    console.error(`Pop: Element "${config.el}" not found`);
    return;
  }

  let counts: Record<string, number> = {};
  let userReactions: string[] = [];

  // Only fetch reactions if emojis are configured
  if (emojis.length > 0) {
    try {
      const data = await fetchReactions(config.endpoint, pageId);
      counts = data.reactions;
      userReactions = data.userReactions;
    } catch (error) {
      console.error("Pop: Failed to fetch reactions", error);
    }
  }

  const handleClick = async (emoji: string, button: HTMLButtonElement) => {
    if (button.disabled) return;

    const wasActive = userReactions.includes(emoji);
    const currentCount = counts[emoji] || 0;

    // Disable button during request
    button.disabled = true;

    // Optimistic update
    const optimisticCount = wasActive ? currentCount - 1 : currentCount + 1;
    updateButton(button, optimisticCount, !wasActive);

    if (wasActive) {
      userReactions = userReactions.filter((e) => e !== emoji);
    } else {
      userReactions.push(emoji);
    }

    try {
      const result = await toggleReaction(config.endpoint, pageId, emoji);
      counts[emoji] = result.count;
      updateButton(button, result.count, result.action === "added");

      // Sync userReactions with server response
      if (result.action === "added" && !userReactions.includes(emoji)) {
        userReactions.push(emoji);
      } else if (result.action === "removed") {
        userReactions = userReactions.filter((e) => e !== emoji);
      }
    } catch (error) {
      console.error("Pop: Failed to toggle reaction", error);
      // Revert optimistic update
      updateButton(button, currentCount, wasActive);
      if (wasActive) {
        userReactions.push(emoji);
      } else {
        userReactions = userReactions.filter((e) => e !== emoji);
      }
    } finally {
      button.disabled = false;
    }
  };

  // Only render buttons if emojis are configured
  if (emojis.length > 0) {
    renderButtons(container, emojis, counts, userReactions, handleClick);
  }

  // Record visit if enabled
  if (config.showVisitors) {
    try {
      const visitResult = await recordVisit(config.endpoint, pageId);
      renderVisitorCount(container, visitResult.uniqueVisitors);
    } catch (error) {
      console.error("Pop: Failed to record visit", error);
    }
  }
}

export { PopConfig } from "./types";
