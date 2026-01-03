/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

import type { PopConfig } from "./types";
import { fetchReactions, toggleReaction } from "./api";
import { renderButtons, updateButton } from "./render";

export async function init(config: PopConfig): Promise<void> {
  const container = document.querySelector<HTMLElement>(config.el);
  if (!container) {
    console.error(`Pop: Element "${config.el}" not found`);
    return;
  }

  const pageId = config.pageId || window.location.href;

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

  renderButtons(container, config.emojis, counts, userReactions, handleClick);
}

export { PopConfig } from "./types";
