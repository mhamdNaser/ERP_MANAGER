import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "../lib";

export function useNotificationActions() {
  const queryClient = useQueryClient();

  const notification = useMutation({
    mutationFn: api.notification,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["dashboard", "notifications"] });
    },
  });

  const readAll = useMutation({
    mutationFn: api.readAllNotifications,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["dashboard", "notifications"] });
    },
  });

  return { notification, readAll };
}
