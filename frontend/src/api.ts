/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

import type { ReactionsResponse, ToggleReactionResponse } from "./types";

export async function fetchReactions(
  endpoint: string,
  pageId: string,
): Promise<ReactionsResponse> {
  const url = `${endpoint}/reactions?pageId=${encodeURIComponent(pageId)}`;
  const response = await fetch(url, {
    method: "GET",
    credentials: "omit",
  });
  if (!response.ok) {
    throw new Error(`Failed to fetch reactions: ${response.status}`);
  }
  return response.json();
}

export async function toggleReaction(
  endpoint: string,
  pageId: string,
  emoji: string,
): Promise<ToggleReactionResponse> {
  const response = await fetch(`${endpoint}/reactions`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "omit",
    body: JSON.stringify({ pageId, emoji }),
  });
  if (!response.ok) {
    throw new Error(`Failed to toggle reaction: ${response.status}`);
  }
  return response.json();
}
