import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "../lib";

export function useSubmitFormPublication() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ publicationId, values }) =>
      api.submitForm(publicationId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["forms"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
