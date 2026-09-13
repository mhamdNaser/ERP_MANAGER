export function toFormData(data) {
  const formData = new FormData();
  Object.entries(data).forEach(([key, value]) => {
    if (value === null || value === undefined || value === "") return;
    if (Array.isArray(value)) {
      value.forEach((item) => {
        if (item === null || item === undefined || item === "") return;
        formData.append(`${key}[]`, item instanceof File ? item : String(item));
      });
      return;
    }
    formData.append(key, value instanceof File ? value : String(value));
  });
  return formData;
}
export function filesFormData(files, data) {
  const formData = toFormData(data);
  files.forEach((file) => formData.append("files[]", file));
  return formData;
}
