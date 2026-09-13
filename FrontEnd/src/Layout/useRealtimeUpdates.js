import { useCallback, useEffect, useState } from "react";
import { createEcho } from "../realtime";

function toTaskNotificationItem(event) {
  return {
    id: event.notification_id,
    title: event.title,
    message: event.message,
    task_id: event.task?.id,
    task: event.task,
    source_type: "task",
    read_at: null,
    created_at: new Date().toISOString(),
  };
}

export function useRealtimeUpdates(userId, unreadBaseline, setNotifications) {
  const [unreadMessageCountOverride, setUnreadMessageCountOverride] =
    useState(null);
  const [taskPopup, setTaskPopup] = useState(null);
  useEffect(() => {
    const echo = createEcho();
    if (!echo) return undefined;
    const channel = echo.private(`user.${userId}`);
    const bump = (event) => {
      if (event.message?.unread_for_user)
        setUnreadMessageCountOverride(
          (count) => (count ?? unreadBaseline ?? 0) + 1,
        );
    };
    const assigned = (event) => {
      setTaskPopup(event);
      setNotifications((items) => [toTaskNotificationItem(event), ...items]);
    };
    channel.listen(".message.created", bump);
    channel.listen(".message.reply.created", bump);
    channel.listen(".task.assigned", assigned);
    return () => {
      echo.leave(`user.${userId}`);
      echo.disconnect();
    };
  }, [unreadBaseline, userId, setNotifications]);
  const clearTaskPopup = useCallback(() => setTaskPopup(null), []);
  return {
    unreadMessageCountOverride,
    setUnreadMessageCountOverride,
    taskPopup,
    clearTaskPopup,
  };
}
