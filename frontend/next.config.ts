import type { NextConfig } from "next";

const backend = (process.env.API_INTERNAL_URL || "http://127.0.0.1:8000/api/v1").replace(/\/api\/v1\/?$/, "");

const nextConfig: NextConfig = {
  turbopack: {root: __dirname},
  outputFileTracingRoot: __dirname,
  output: "standalone",
  poweredByHeader: false,
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "images.unsplash.com" },
    ],
  },
  async rewrites() {
    return [
      { source: "/api/v1/:path*", destination: `${backend}/api/v1/:path*` },
      { source: "/sanctum/:path*", destination: `${backend}/sanctum/:path*` },
      { source: "/storage/:path*", destination: `${backend}/storage/:path*` },
    ];
  },
  async headers() {
    return [{ source: "/:path*", headers: [
      { key: "X-Content-Type-Options", value: "nosniff" },
      { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
      { key: "X-Frame-Options", value: "DENY" },
      { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
    ] }, ...["/account/:path*", "/checkout", "/cart"].map(source => ({source, headers: [
      {key: "Cache-Control", value: "private, no-store, max-age=0"},
      {key: "X-Robots-Tag", value: "noindex, nofollow"},
      {key: "Referrer-Policy", value: "no-referrer"},
    ]}))];
  },
};

export default nextConfig;
