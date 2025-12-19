import createMDX from '@next/mdx';
import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  pageExtensions: ['js', 'jsx', 'mdx', 'ts', 'tsx'],
  images: {
    unoptimized: true,
  },

  // Production optimizations
  poweredByHeader: false,
  reactStrictMode: true,

  // Environment variables available on client
  env: {
    NEXT_PUBLIC_LARAVEL_API_URL: process.env.NEXT_PUBLIC_LARAVEL_API_URL,
  },

  // Experimental features for better performance
  experimental: {
    optimizePackageImports: ['lucide-react', 'react-icons'],
  },

  // Output configuration for Vercel
  output: 'standalone',
};
const withMDX = createMDX({
  // Add markdown plugins here, if needed
  options: {
    remarkPlugins: [],
    rehypePlugins: [],
  },
});

export default withMDX(nextConfig);
