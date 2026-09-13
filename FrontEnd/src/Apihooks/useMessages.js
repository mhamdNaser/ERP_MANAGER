import { useQuery } from "@tanstack/react-query";
import { api } from "../lib";

export function useUnreadMessageCount(permissions = []) {
  const enabled =
    permissions.includes("messages.view") ||
    permissions.includes("correspondences.view");

  return useQuery({
    queryKey: ["messages", "unread-count"],
    queryFn: api.messageUnreadCount,
    enabled,
  });
}
