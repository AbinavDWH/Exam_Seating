import { createContext, useCallback, useContext, useState } from 'react';

const ToastCtx = createContext(() => {});
export const useToast = () => useContext(ToastCtx);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const push = useCallback((message) => {
    const id = Date.now() + Math.random();
    setToasts((t) => [...t, { id, message }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 2800);
  }, []);

  return (
    <ToastCtx.Provider value={push}>
      {children}
      <div className="toasts no-print" aria-live="polite">
        {toasts.map((t) => <div key={t.id} className="toast">{t.message}</div>)}
      </div>
    </ToastCtx.Provider>
  );
}