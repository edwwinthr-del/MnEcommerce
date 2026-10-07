import { AuthForm } from "@/components/auth-form";
export const metadata = {title: "Choose a new password", referrer: "no-referrer"};
export const dynamic = "force-dynamic";
export default async function Page({searchParams}: {searchParams: Promise<{email?: string; token?: string}>}) {
  const query = await searchParams;
  return <AuthForm mode="reset-password" email={typeof query.email === "string" ? query.email.slice(0,254) : ""} token={typeof query.token === "string" ? query.token.slice(0,200) : ""}/>;
}
