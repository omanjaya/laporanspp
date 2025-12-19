import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-sm text-sm font-semibold transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        default: 'bg-primary text-gray-900 text-body-md',
        destructive:
          'bg-destructive text-destructive-foreground shadow-xs hover:bg-destructive/90',
        outline: 'border border-input bg-background shadow-xs  ',
        toggle:
          'border rounded-full border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground',
        secondary:
          'bg-background text-gray-900 text-body-md border border-[#DFE1E6]',
        ghost:
          'hover:bg-accent hover:text-accent-foreground border border-dark-gray text-foreground',
        link: 'underline-offset-4 hover:underline text-foreground font-medium',
        translucent:
          'rounded-[16px] h-[52px] text-body-md text-white ' +
          'border border-gray-0/15 bg-gray-0/15 ' +
          'shadow-[0_1px_2px_0_rgba(13,13,18,0.06)] backdrop-blur-[2px] ' +
          'hover:bg-gray-0/20 active:bg-gray-0/25 focus-visible:ring-gray-0/40',
      },
      size: {
        default: 'p-4 h-[52px] rounded-[16px]',
        sm: 'h-10 rounded-[12px] px-4 py-3 text-xs',
        lg: 'h-10 rounded-lg px-8',
        icon: 'size-9',
        link: 'h-auto p-0',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return (
      <Comp
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      />
    );
  },
);
Button.displayName = 'Button';

export { Button, buttonVariants };
