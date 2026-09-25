/** The server's message for a failed request (Laravel `{ message }`), else `fallback`. */
export const errorMessage = (error, fallback) => error?.response?.data?.message || fallback
