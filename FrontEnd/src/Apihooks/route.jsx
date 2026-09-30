import { createBrowserRouter } from "react-router-dom";
import App from "../App";

export const tabRouteIds = [
  "dashboard",
  "tasks",
  "task-stats",
  "drive",
  "reports",
  "organization",
  "org-tree",
  "communications",
  "formal-correspondences",
  "circulars",
  "forms",
  "offices",
  "database",
  "document-templates",
  "hr-requests",
  "hr",
  "fleet",
  "maintenance",
  "employees",
  "profile",
  "guide",
  "permissions",
  "notification",
];

export const router = createBrowserRouter([
  {
    path: "/",
    element: <App />,
  },
  {
    path: "/:tab",
    element: <App />,
  },
]);

export default router;
