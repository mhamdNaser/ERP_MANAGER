import { useCallback, useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "../lib";

export function useDashboardData(permissions = [], notify = () => {}) {
  const queryClient = useQueryClient();
  const [data, setData] = useState(null);
  const [reports, setReports] = useState([]);
  const [notifications, setNotifications] = useState([]);

  const dashboardQuery = useQuery({
    queryKey: ["dashboard", "stats"],
    queryFn: api.dashboard,
  });

  const reportsQuery = useQuery({
    queryKey: ["dashboard", "reports"],
    queryFn: api.reports,
    enabled: permissions.includes("reports.view"),
  });

  const notificationsQuery = useQuery({
    queryKey: ["dashboard", "notifications"],
    queryFn: api.notifications,
    enabled: permissions.includes("notifications.view"),
  });

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (dashboardQuery.data) setData(dashboardQuery.data);
  }, [dashboardQuery.data]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (reportsQuery.data) setReports(reportsQuery.data);
  }, [reportsQuery.data]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (notificationsQuery.data) setNotifications(notificationsQuery.data);
  }, [notificationsQuery.data]);

  useEffect(() => {
    const error =
      dashboardQuery.error || reportsQuery.error || notificationsQuery.error;
    if (error) notify(error.message, "error");
  }, [dashboardQuery.error, reportsQuery.error, notificationsQuery.error, notify]);

  const load = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ["dashboard"] });
  }, [queryClient]);

  return { data, reports, setReports, notifications, setNotifications, load };
}
