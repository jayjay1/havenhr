"use client";

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from "react";
import { useRouter } from "next/navigation";
import { ApiRequestError } from "@/lib/api";
import {
  candidateApiClient,
  getCandidateAccessToken,
  setCandidateAccessToken,
  clearCandidateAccessToken,
} from "@/lib/candidateApi";
import type {
  Candidate,
  CandidateLoginResponse,
  CandidateRegisterResponse,
  CandidateMeResponse,
} from "@/types/candidate";

/**
 * Candidate auth context value provided to consumers.
 */
export interface CandidateAuthContextValue {
  /** Current authenticated candidate, null while loading or if unauthenticated */
  candidate: Candidate | null;
  /** Whether the candidate is authenticated */
  isAuthenticated: boolean;
  /** Whether auth state is still loading */
  isLoading: boolean;
  /** Register a new candidate */
  register: (data: {
    name: string;
    email: string;
    password: string;
  }) => Promise<void>;
  /** Log in an existing candidate */
  login: (data: { email: string; password: string }) => Promise<void>;
  /** Log out the current candidate */
  logout: () => Promise<void>;
  /** Refresh candidate data from the server */
  refresh: () => Promise<void>;
}

const CandidateAuthContext = createContext<CandidateAuthContextValue | undefined>(
  undefined
);

/**
 * CandidateAuthProvider wraps candidate routes and provides auth state.
 * Fetches candidate info from /candidate/auth/me on mount.
 * Separate from the tenant AuthProvider.
 */
export function CandidateAuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const [candidate, setCandidate] = useState<Candidate | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const fetchCandidate = useCallback(async () => {
    const token = getCandidateAccessToken();
    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      const response = await candidateApiClient.get<CandidateMeResponse>(
        "/candidate/auth/me"
      );
      const me = response.data;
      setCandidate({
        id: me.id,
        name: me.name,
        email: me.email,
        phone: me.phone,
        location: me.location,
        linkedin_url: me.linkedin_url,
        portfolio_url: me.portfolio_url,
        is_active: me.is_active,
        email_verified_at: me.email_verified_at,
        last_login_at: me.last_login_at,
        created_at: me.created_at,
        updated_at: me.updated_at,
      });
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        clearCandidateAccessToken();
        router.push("/candidate/login");
      }
    } finally {
      setIsLoading(false);
    }
  }, [router]);

  useEffect(() => {
    fetchCandidate();
  }, [fetchCandidate]);

  const register = useCallback(
    async (data: { name: string; email: string; password: string }) => {
      const response = await candidateApiClient.post<CandidateRegisterResponse>(
        "/candidate/auth/register",
        data as unknown as Record<string, unknown>
      );
      setCandidateAccessToken(response.data.access_token);
      // Fetch full candidate profile after registration
      await fetchCandidate();
      router.push("/candidate/dashboard");
    },
    [router, fetchCandidate]
  );

  const login = useCallback(
    async (data: { email: string; password: string }) => {
      const response = await candidateApiClient.post<CandidateLoginResponse>(
        "/candidate/auth/login",
        data as unknown as Record<string, unknown>
      );
      setCandidateAccessToken(response.data.access_token);
      // Fetch full candidate profile after login
      await fetchCandidate();
      router.push("/candidate/dashboard");
    },
    [router, fetchCandidate]
  );

  const logout = useCallback(async () => {
    try {
      await candidateApiClient.post("/candidate/auth/logout");
    } catch {
      // Proceed with client-side cleanup even if API call fails
    }
    clearCandidateAccessToken();
    setCandidate(null);
    router.push("/candidate/login");
  }, [router]);

  const refresh = useCallback(async () => {
    await fetchCandidate();
  }, [fetchCandidate]);

  const value: CandidateAuthContextValue = {
    candidate,
    isAuthenticated: !!candidate,
    isLoading,
    register,
    login,
    logout,
    refresh,
  };

  return (
    <CandidateAuthContext.Provider value={value}>
      {children}
    </CandidateAuthContext.Provider>
  );
}

/**
 * Hook to access candidate auth context. Must be used within a CandidateAuthProvider.
 */
export function useCandidateAuth(): CandidateAuthContextValue {
  const context = useContext(CandidateAuthContext);
  if (context === undefined) {
    throw new Error(
      "useCandidateAuth must be used within a CandidateAuthProvider"
    );
  }
  return context;
}
