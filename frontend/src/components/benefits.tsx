import { PackageCheck, ShieldCheck, Truck, WalletCards } from "lucide-react";

export function Benefits() {
  return <section className="benefits container" aria-label="Shopping with Mora">{[
    {icon: Truck, title: "Across Montenegro", text: "Delivery estimates on every product"},
    {icon: PackageCheck, title: "Thoughtfully selected", text: "Useful things for real, everyday life"},
    {icon: WalletCards, title: "Pay your way", text: "Cash on delivery or bank transfer"},
    {icon: ShieldCheck, title: "Clear from the start", text: "Review every cost before ordering"},
  ].map(({icon: Icon, title, text}) => <div key={title}><Icon size={25} strokeWidth={1.4}/><div><h3>{title}</h3><p>{text}</p></div></div>)}</section>;
}
