import Link from 'next/link';

import { Button } from '@/components/ui/button';

export default function NotFound() {
  return (
    <>
      <section className="bg-primary-300 h-[80vh] px-6 text-white">
        <div className="container h-full">
          <div className="mx-auto flex h-full max-w-3xl flex-col items-center justify-center text-center">
            <span className="bg-gray-0/10 inline-flex h-7 items-center rounded-[10px] border border-white/15 px-3 text-sm text-white/90 shadow-[0_1px_2px_0_rgba(13,13,18,0.06)] backdrop-blur-[2px]">
              404
            </span>

            <h1 className="mt-4 text-5xl leading-[1.08] font-semibold tracking-tight sm:text-6xl">
              Page Not Found
            </h1>

            <p className="text-body-lg mx-auto mt-4 max-w-2xl text-white/80">
              Sorry, we couldn’t find the page you’re looking for. It might have
              been moved, renamed, or no longer exists.
            </p>

            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <Button asChild className="w-full text-gray-900 sm:w-auto">
                <Link href="/">Go Home</Link>
              </Button>

              <Button
                asChild
                variant="translucent"
                className="w-full text-white sm:w-auto"
              >
                <Link href="/contact">Contact Support</Link>
              </Button>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
