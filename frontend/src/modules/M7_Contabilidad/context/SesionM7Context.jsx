import { createContext, useContext } from 'react';

export const SesionM7Context = createContext(null);

export function useSesionM7() {
  const contexto = useContext(SesionM7Context);

  if (!contexto) {
    throw new Error(
      'useSesionM7 debe utilizarse dentro de SesionM7Context.Provider'
    );
  }

  return contexto;
}