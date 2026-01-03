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
  const needsContainer = config.renderVisits || config.renderReactions;

  // Validate config
  if (config.renderReactions && emojis.length === 0) {
    console.error("Pop: renderReactions requires emojis to be defined");
    return;
  }

  // Get container if needed for rendering
  let container: HTMLElement | null = null;
  if (needsContainer) {
    if (!config.el) {
      console.error(
        "Pop: el is required when renderVisits or renderReactions is enabled",
      );
      return;
    }
    container = document.querySelector<HTMLElement>(config.el);
    if (!container) {
      console.error(`Pop: Element "${config.el}" not found`);
      return;
    }
  }

  // Track visits
  let uniqueVisitors = 0;
  if (config.trackVisits) {
    try {
      const visitResult = await recordVisit(config.endpoint, pageId);
      uniqueVisitors = visitResult.uniqueVisitors;
    } catch (error) {
      console.error("Pop: Failed to record visit", error);
    }
  }

  // Render visits
  if (config.renderVisits && container) {
    renderVisitorCount(container, uniqueVisitors);
  }

  // Render reactions
  if (config.renderReactions && container && emojis.length > 0) {
    let counts: Record<string, number> = {};
    let userReactions: string[] = [];

    try {
      const data = await fetchReactions(config.endpoint, pageId);
      counts = data.reactions;
      userReactions = data.userReactions;
    } catch (error) {
      console.error("Pop: Failed to fetch reactions", error);
    }

    const handleClick = async (emoji: string, button: HTMLButtonElement) => {
      if (button.disabled) return;

      const wasActive = userReactions.includes(emoji);
      const currentCount = counts[emoji] || 0;

      button.disabled = true;

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

        if (result.action === "added" && !userReactions.includes(emoji)) {
          userReactions.push(emoji);
        } else if (result.action === "removed") {
          userReactions = userReactions.filter((e) => e !== emoji);
        }
      } catch (error) {
        console.error("Pop: Failed to toggle reaction", error);
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

    renderButtons(container, emojis, counts, userReactions, handleClick);
  }
}

export { PopConfig } from "./types";
