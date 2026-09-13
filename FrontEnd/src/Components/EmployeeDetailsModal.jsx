import { useLanguage } from "../Provider/LanguageContext";
import { api } from "../lib";
import { ReusableFormModal } from "./ReusableFormModal";
export function EmployeeDetailsModal({ employee, self, close, done, notify }) {
  const { t } = useLanguage();
  const fields = [
    { name: "birth_date", label: t("birthDate"), type: "date" },
    { name: "national_id", label: t("nationalId") },
    {
      name: "gender",
      label: t("gender"),
      type: "select",
      options: [
        { value: "male", label: t("male") },
        { value: "female", label: t("female") },
      ],
    },
    { name: "blood_type", label: t("bloodType") },
    { name: "height_cm", label: t("heightCm"), type: "number" },
    { name: "weight_kg", label: t("weightKg"), type: "number" },
    { name: "shoe_size", label: t("shoeSize") },
    { name: "trouser_size", label: t("trouserSize") },
    { name: "shirt_size", label: t("shirtSize") },
    { name: "jacket_size", label: t("jacketSize") },
    {
      name: "uniform_notes",
      label: t("uniformNotes"),
      type: "textarea",
      wide: true,
    },
    { name: "country", label: t("country") },
    { name: "city", label: t("city") },
    { name: "district", label: t("district") },
    { name: "street", label: t("street") },
    { name: "building", label: t("building") },
    {
      name: "address_details",
      label: t("addressDetails"),
      type: "textarea",
      wide: true,
    },
    {
      name: "marital_status",
      label: t("maritalStatus"),
      type: "select",
      options: [
        { value: "single", label: t("single") },
        { value: "married", label: t("married") },
        { value: "divorced", label: t("divorced") },
        { value: "widowed", label: t("widowed") },
      ],
    },
    { name: "spouse_name", label: t("spouseName") },
    { name: "children_count", label: t("childrenCount"), type: "number" },
    { name: "emergency_contact_name", label: t("emergencyContactName") },
    { name: "emergency_contact_phone", label: t("emergencyContactPhone") },
    {
      name: "emergency_contact_relation",
      label: t("emergencyContactRelation"),
    },
  ];
  const initial = {
    ...(employee.personal_details || {}),
    ...(employee.address || {}),
    ...(employee.family_details || {}),
    address_details: employee.address?.details || "",
  };
  const submit = async (values) => {
    const payload = {
      personal_details: pick(values, [
        "birth_date",
        "national_id",
        "gender",
        "blood_type",
        "height_cm",
        "weight_kg",
        "shoe_size",
        "trouser_size",
        "shirt_size",
        "jacket_size",
        "uniform_notes",
      ]),
      address: {
        ...pick(values, ["country", "city", "district", "street", "building"]),
        details: String(values.address_details || ""),
      },
      family_details: pick(values, [
        "marital_status",
        "spouse_name",
        "children_count",
        "emergency_contact_name",
        "emergency_contact_phone",
        "emergency_contact_relation",
      ]),
    };
    try {
      const updated = self
        ? await api.updateMyDetails(payload)
        : await api.updateEmployeeDetails(employee.id, payload);
      notify(t("detailsSaved"), "success");
      done(updated);
    } catch (error) {
      notify(error.message, "error");
      throw error;
    }
  };
  return (
    <ReusableFormModal
      title={`${t("employeeDetails")} · ${employee.name}`}
      subtitle={t("employeeDetailsHint")}
      fields={fields}
      initial={initial}
      onSubmit={submit}
      onClose={close}
    />
  );
}
function pick(values, keys) {
  return Object.fromEntries(keys.map((key) => [key, values[key] ?? ""]));
}
