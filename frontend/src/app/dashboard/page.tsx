"use client";

import { useAuth } from "@/contexts/AuthContext";

export default function DashboardPage() {
  const { user } = useAuth();

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
      {user && (
        <p className="mt-2 text-gray-600">
          Welcome back, {user.name}.
        </p>
      )}
    </div>
  );
}
