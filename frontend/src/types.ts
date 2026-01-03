/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

export interface PopConfig {
  el: string;
  endpoint: string;
  emojis?: string[];
  pageId?: string;
  showVisitors?: boolean;
  silent?: boolean;
}

export interface ReactionsResponse {
  pageId: string;
  reactions: Record<string, number>;
  userReactions: string[];
}

export interface ToggleReactionResponse {
  success: boolean;
  action: "added" | "removed";
  count: number;
}

export interface VisitResponse {
  success: boolean;
  recorded: boolean;
  uniqueVisitors: number;
}

export interface VisitsResponse {
  pageId: string;
  uniqueVisitors: number;
  totalVisits: number;
}
