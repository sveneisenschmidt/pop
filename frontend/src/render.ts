/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

export function renderButtons(
  container: HTMLElement,
  emojis: string[],
  counts: Record<string, number>,
  userReactions: string[],
  onClick: (emoji: string, button: HTMLButtonElement) => void,
): void {
  container.innerHTML = "";
  const wrapper = document.createElement("div");
  wrapper.className = "pop-reactions";

  for (const emoji of emojis) {
    const button = document.createElement("button");
    button.className = "pop-btn";
    button.dataset.emoji = emoji;

    if (userReactions.includes(emoji)) {
      button.classList.add("pop-btn--active");
    }

    const count = counts[emoji] || 0;
    button.textContent = emoji + " ";
    const span = document.createElement("span");
    span.textContent = String(count);
    button.appendChild(span);

    button.addEventListener("click", () => onClick(emoji, button));

    wrapper.appendChild(button);
  }

  container.appendChild(wrapper);
}

export function updateButton(
  button: HTMLButtonElement,
  newCount: number,
  isActive: boolean,
): void {
  const span = button.querySelector("span");
  if (span) {
    span.textContent = String(newCount);
  }
  button.classList.toggle("pop-btn--active", isActive);
}

export function renderVisitorCount(
  container: HTMLElement,
  count: number,
): void {
  let wrapper = container.querySelector(".pop-reactions") as HTMLElement;
  if (!wrapper) {
    wrapper = document.createElement("div");
    wrapper.className = "pop-reactions";
    container.appendChild(wrapper);
  }

  let visitorEl = wrapper.querySelector(".pop-visitors") as HTMLElement;
  if (!visitorEl) {
    visitorEl = document.createElement("span");
    visitorEl.className = "pop-btn pop-visitors";
    wrapper.insertBefore(visitorEl, wrapper.firstChild);
  }

  visitorEl.textContent = "👀 ";
  const span = document.createElement("span");
  span.textContent = String(count);
  visitorEl.appendChild(span);
}

export function updateVisitorCount(
  container: HTMLElement,
  count: number,
): void {
  const visitorEl = container.querySelector(".pop-visitors");
  if (visitorEl) {
    visitorEl.textContent = "👀 ";
    const span = document.createElement("span");
    span.textContent = String(count);
    visitorEl.appendChild(span);
  }
}
