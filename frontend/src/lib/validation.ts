import { z } from "zod";

export const checkoutSchema = z.object({
  customer_name: z.string().trim().min(2, "Enter your full name.").max(120),
  customer_email: z.email("Enter a valid email address.").max(254),
  customer_phone: z.string().trim().regex(/^\+?[0-9 ()-]{7,25}$/, "Enter a phone number with 7–25 digits or spaces."),
  shipping_address: z.string().trim().min(3, "Enter your street and house number.").max(240),
  shipping_city: z.string().trim().min(2, "Enter your city.").max(120),
  shipping_postal_code: z.string().regex(/^[0-9]{5}$/, "Enter a five-digit postal code."),
  shipping_country: z.literal("ME"),
  payment_method: z.enum(["cash_on_delivery", "bank_transfer"]),
  customer_note: z.string().max(1000, "Keep your note within 1,000 characters."),
});
export type CheckoutFields = z.infer<typeof checkoutSchema>;

export const passwordSchema = z.string().min(12, "Use at least 12 characters.").refine(value => new TextEncoder().encode(value).length <= 72, "Use a password no longer than 72 bytes.");
export const authSchema = z.object({
  name: z.string().max(120).optional(),
  email: z.email("Enter a valid email address.").max(254),
  password: z.string().optional(),
  password_confirmation: z.string().optional(),
});
export type AuthFields = z.infer<typeof authSchema>;
export type AuthMode = "login" | "register" | "forgot-password" | "reset-password";
export function schemaForAuth(mode: AuthMode) {
  return authSchema.superRefine((value, context) => {
    if (mode === "register" && (!value.name || value.name.trim().length < 2)) context.addIssue({code: "custom", path: ["name"], message: "Enter your full name."});
    if (mode === "login" && !value.password) context.addIssue({code: "custom", path: ["password"], message: "Enter your password."});
    if (mode === "register" || mode === "reset-password") {
      const password = passwordSchema.safeParse(value.password ?? "");
      if (!password.success) context.addIssue({code: "custom", path: ["password"], message: password.error.issues[0].message});
      if (value.password !== value.password_confirmation) context.addIssue({code: "custom", path: ["password_confirmation"], message: "Your passwords do not match."});
    }
  });
}
