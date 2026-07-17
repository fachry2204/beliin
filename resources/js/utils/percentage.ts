export const percentageNumber = (value: string | number) =>
    Math.min(100, Math.max(0, Math.round(Number(value) || 0)));

export const percentageText = (value: string | number) =>
    `${percentageNumber(value)}%`;
