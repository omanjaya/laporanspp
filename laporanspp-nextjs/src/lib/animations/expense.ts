import type { AnimationSequence } from 'framer-motion';

export const expenseSequence: AnimationSequence = [
  [
    '#expense-1',
    { opacity: [0, 1], x: [-24, 0] },
    { duration: 0.6, ease: 'easeOut' },
  ],
  [
    '#expense-2',
    { opacity: [0, 1], x: [24, 0] },
    { duration: 0.6, ease: 'easeOut', at: '+0.2' },
  ],
];
