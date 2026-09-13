import { useEffect } from "react";

/** Opens the task matching focusTaskId once it appears on the loaded board, then clears the request. */
export function useFocusTask(board, focusTaskId, onTaskFocused, setSelected) {
  useEffect(() => {
    if (!focusTaskId || !board) return;
    const task = board.tasks.find((item) => item.id === focusTaskId);
    if (task) setSelected(task);
    onTaskFocused?.();
  }, [board, focusTaskId, onTaskFocused, setSelected]);
}
