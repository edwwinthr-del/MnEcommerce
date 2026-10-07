"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { ApiError, postJson } from "@/lib/api";
import { schemaForAuth, type AuthFields, type AuthMode } from "@/lib/validation";

const copy = {
  login: {title: "Good to see you again.", intro: "Sign in to your Mora account.", button: "Sign in"},
  register: {title: "Make yourself at home.", intro: "Create your customer account. You can always check out as a guest.", button: "Create account"},
  "forgot-password": {title: "Let’s get you back in.", intro: "Enter your email and we’ll send a password reset link if it belongs to an account.", button: "Send reset link"},
  "reset-password": {title: "A fresh start.", intro: "Choose a new password with at least 12 characters.", button: "Reset password"},
};

export function AuthForm({mode, email = "", token = ""}: {mode: AuthMode; email?: string; token?: string}) {
  const router = useRouter();
  const [message, setMessage] = useState("");
  const [success, setSuccess] = useState(false);
  const {register, handleSubmit, setError, formState: {errors, isSubmitting}} = useForm<AuthFields>({resolver: zodResolver(schemaForAuth(mode)), defaultValues: {email}});
  const needsNewPassword = mode === "register" || mode === "reset-password";
  const invalidLink = mode === "reset-password" && !token;
  const submit = handleSubmit(async values => {
    setMessage("");
    try {
      const body = mode === "forgot-password" ? {email: values.email} : mode === "reset-password" ? {email: values.email, password: values.password, password_confirmation: values.password_confirmation, token} : mode === "login" ? {email: values.email, password: values.password} : {...values, name: values.name?.trim()};
      await postJson(`/auth/${mode}`, body);
      if (mode === "login" || mode === "register") { router.replace("/account"); router.refresh(); }
      else { setSuccess(true); setMessage(mode === "forgot-password" ? "If an account matches that address, a reset link will arrive by email. Check your spam folder too." : "Your password has been reset. You can now sign in with your new password."); }
    } catch (error) {
      if (error instanceof ApiError) for (const [name, messages] of Object.entries(error.errors)) if (["name", "email", "password", "password_confirmation"].includes(name)) setError(name as keyof AuthFields, {message: messages[0]}, {shouldFocus: true});
      setMessage(error instanceof Error ? error.message : "We could not complete that request. Please try again.");
    }
  });
  return <section className="account-shell"><p className="eyebrow">YOUR MORA ACCOUNT</p><h1>{copy[mode].title}</h1><p>{copy[mode].intro}</p>{invalidLink ? <div className="form-error" role="alert">This reset link is incomplete. <Link className="underline" href="/account/forgot-password">Request a new link</Link>.</div> : success ? <div className="form-success" role="status">{message}</div> : <form onSubmit={submit} noValidate>{mode === "register" && <div className="field"><label htmlFor="name">Full name</label><input id="name" autoComplete="name" maxLength={120} {...register("name")} aria-invalid={!!errors.name} aria-describedby={errors.name ? "name-error" : undefined}/>{errors.name && <span id="name-error" className="field-error">{errors.name.message}</span>}</div>}<div className="field"><label htmlFor="email">Email address</label><input id="email" type="email" autoComplete="email" maxLength={254} {...register("email")} aria-invalid={!!errors.email} aria-describedby={errors.email ? "email-error" : undefined}/>{errors.email && <span id="email-error" className="field-error">{errors.email.message}</span>}</div>{mode !== "forgot-password" && <div className="field"><label htmlFor="password">{needsNewPassword ? "New password" : "Password"}</label><input id="password" type="password" autoComplete={needsNewPassword ? "new-password" : "current-password"} {...register("password")} aria-invalid={!!errors.password} aria-describedby={errors.password ? "password-error" : needsNewPassword ? "password-help" : undefined}/>{needsNewPassword && <p id="password-help" className="field-note">Use at least 12 characters. A unique passphrase works well.</p>}{errors.password && <span id="password-error" className="field-error">{errors.password.message}</span>}</div>}{needsNewPassword && <div className="field"><label htmlFor="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" autoComplete="new-password" {...register("password_confirmation")} aria-invalid={!!errors.password_confirmation} aria-describedby={errors.password_confirmation ? "confirmation-error" : undefined}/>{errors.password_confirmation && <span id="confirmation-error" className="field-error">{errors.password_confirmation.message}</span>}</div>}{mode === "register" && <p className="checkout-consent">Creating an account means accepting our <Link href="/terms">terms</Link>. Read our <Link href="/privacy">privacy notice</Link> for how your information is used.</p>}{message && <div className="form-error" role="alert">{message}</div>}<button type="submit" className="button button-dark button-wide" disabled={isSubmitting}>{isSubmitting ? "Please wait…" : copy[mode].button}</button></form>}<div className="account-links"><Link href={mode === "login" ? "/account/register" : "/account/login"}>{mode === "login" ? "Create an account" : "Back to sign in"}</Link>{mode === "login" ? <Link href="/account/forgot-password">Forgot password?</Link> : <Link href="/shop">Continue shopping</Link>}</div></section>;
}
