/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

export interface PopPageInfo {
  pageId: string;
  reactions: Record<string, number>;
  userReactions: string[];
  uniqueVisitors: number;
  totalVisits: number;
}

export interface PopConfig {
  endpoint: string;
  pageId?: string;
  el?: string;
  emojis?: string[];
  trackVisits?: boolean;
  renderVisits?: boolean;
  renderReactions?: boolean;
  onLoad?: (pageInfo: PopPageInfo) => void;
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
