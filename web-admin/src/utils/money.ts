export const fen2yuan = (fen: number | string | null | undefined): string =>
  ((Number(fen) || 0) / 100).toFixed(2)

export const yuan2fen = (yuan: number | string | null | undefined): number =>
  Math.round((Number(yuan) || 0) * 100)
