import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'export',  // Static Export - generuje statyczne pliki HTML/CSS/JS
  reactStrictMode: true,
  images: {
    unoptimized: true,  // Wymagane dla static export
  },
  // Wyłączamy API routes - backend będzie w PHP
  trailingSlash: true,
};

export default nextConfig;
