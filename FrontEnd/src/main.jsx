import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { QueryClientProvider } from "@tanstack/react-query";
import { RouterProvider } from "react-router-dom";
import "./index.css";
import router from "./Apihooks/route.jsx";
import { queryClient } from "./Apihooks/queryClient";
const initialLanguage = localStorage.getItem("LANGUAGE") || "ar";
document.documentElement.lang = initialLanguage;
document.documentElement.dir = initialLanguage === "ar" ? "rtl" : "ltr";
createRoot(document.getElementById("root")).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  </StrictMode>,
);
