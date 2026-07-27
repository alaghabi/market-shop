import { useState, useCallback } from "react";
import { useApiClient, useApiData } from "../../hooks/useApi";
import { Card, CardHeader, CardBody } from "../../components/Card";
import { Button } from "../../components/Button";
import { Table } from "../../components/Table";
import { Badge } from "../../components/Badge";
import { Pagination } from "../../components/Pagination";
import { Modal } from "../../components/Modal";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { FormField, Input, Select } from "../../components/FormField";
import { LoadingState, EmptyState, ErrorState } from "../../components/States";
import { PageHeader } from "../../layout/Shell";
import { useNotification } from "../../hooks/useNotification";

type Employee = {
  id: string;
  userId: string;
  email: string;
  displayName?: string | null;
  boutiqueId: string;
  boutiqueName: string;
  role: string;
  status?: string;
  createdAt: string;
};

const PAGE_SIZE = 20;

export function EmployeesPage({
  getAccessToken,
}: {
  getAccessToken: () => string | null;
}) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Employee | null>(null);
  const [editing, setEditing] = useState<Employee | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [form, setForm] = useState({
    email: "",
    displayName: "",
    password: "",
    roles: "ROLE_CAISSIER",
  });

  const fetchData = useCallback(async () => {
    return api.getCollection<Employee>("/admin/user-shops");
  }, [api]);

  const { data, isLoading, error, refresh } = useApiData(fetchData);
  const employees = (data?.member ?? []).filter(
    (employee) =>
      employee.role === "ROLE_CAISSIER" &&
      (!search ||
        employee.email.toLowerCase().includes(search.toLowerCase()) ||
        employee.displayName?.toLowerCase().includes(search.toLowerCase())),
  );
  const totalItems = employees.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
  const visibleEmployees = employees.slice(
    (page - 1) * PAGE_SIZE,
    page * PAGE_SIZE,
  );

  function openCreate() {
    setEditing(null);
    setForm({
      email: "",
      displayName: "",
      password: "",
      roles: "ROLE_CAISSIER",
    });
    setModalOpen(true);
  }
  function openEdit(e: Employee) {
    setEditing(e);
    setForm({
      email: e.email,
      displayName: e.displayName ?? "",
      password: "",
      roles: e.role ?? "ROLE_CAISSIER",
    });
    setModalOpen(true);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const body: any = {
        email: form.email,
        displayName: form.displayName || null,
        roles: [form.roles],
      };
      if (form.password) body.password = form.password;
      if (editing) {
        await api.patch("/users/" + editing.id, body);
        showNotice("Employé mis à jour.", "success");
      } else {
        await api.post("/users", body);
        showNotice("Employé créé.", "success");
      }
      setModalOpen(false);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Erreur.", "error");
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    try {
      await api.delete("/users/" + deleteTarget.id);
      showNotice("Employé supprimé.", "success");
      setDeleteTarget(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Erreur.", "error");
    }
  }

  async function toggleStatus(employee: Employee): Promise<void> {
    const nextStatus = employee.status === "active" ? "suspended" : "active";
    setActionLoading(employee.id);

    try {
      await api.patch(`/admin/user-shops/${employee.id}`, {
        status: nextStatus,
      });
      showNotice(
        nextStatus === "active" ? "Employé activé." : "Employé désactivé.",
        "success",
      );
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Erreur.", "error");
    } finally {
      setActionLoading(null);
    }
  }

  const columns = [
    {
      key: "email",
      label: "Email",
      render: (e: Employee) => <strong>{e.email}</strong>,
    },
    {
      key: "displayName",
      label: "Nom",
      render: (e: Employee) => <span>{e.displayName ?? "—"}</span>,
    },
    {
      key: "boutiqueName",
      label: "Boutique",
      render: (e: Employee) => <span>{e.boutiqueName}</span>,
    },
    {
      key: "role",
      label: "Rôle",
      render: (e: Employee) => <Badge tone="neutral">Caissier</Badge>,
    },
    {
      key: "status",
      label: "Statut",
      render: (e: Employee) => (
        <Badge tone={e.status === "active" ? "success" : "warning"}>
          {e.status === "active" ? "Actif" : "Désactivé"}
        </Badge>
      ),
    },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader
        title="Employés"
        description="Gérez les accès"
        actions={<Button onClick={openCreate}>+ Inviter</Button>}
      />
      <Card>
        <CardHeader>
          <input
            className="bo-input"
            style={{ maxWidth: 320 }}
            placeholder="Rechercher..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <LoadingState />
          ) : employees.length === 0 ? (
            <EmptyState
              title="Aucun employé"
              message="Invitez votre premier employé."
              action={{ label: "+ Inviter", onClick: openCreate }}
            />
          ) : (
            <>
              <Table
                columns={columns}
                data={visibleEmployees}
                onRowClick={openEdit}
                renderActions={(employee) => (
                  <Button
                    size="sm"
                    variant={
                      employee.status === "active" ? "danger" : "secondary"
                    }
                    disabled={actionLoading === employee.id}
                    onClick={(event) => {
                      event.stopPropagation();
                      void toggleStatus(employee);
                    }}
                  >
                    {actionLoading === employee.id
                      ? "..."
                      : employee.status === "active"
                        ? "Désactiver"
                        : "Activer"}
                  </Button>
                )}
              />
              <Pagination
                page={page}
                totalPages={totalPages}
                onPageChange={setPage}
              />
            </>
          )}
        </CardBody>
      </Card>

      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? "Modifier" : "Inviter"}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>
              Annuler
            </Button>
            <Button onClick={handleSubmit} disabled={submitting}>
              {submitting ? "..." : editing ? "Mettre à jour" : "Inviter"}
            </Button>
          </>
        }
      >
        <form className="bo-form" onSubmit={handleSubmit}>
          <FormField label="Email" required>
            <Input
              type="email"
              required
              value={form.email}
              onChange={(e) =>
                setForm((f) => ({ ...f, email: e.target.value }))
              }
            />
          </FormField>
          <FormField label="Nom affiché">
            <Input
              value={form.displayName}
              onChange={(e) =>
                setForm((f) => ({ ...f, displayName: e.target.value }))
              }
            />
          </FormField>
          {!editing && (
            <FormField
              label="Mot de passe"
              hint="Laissez vide pour générer un lien"
            >
              <Input
                type="password"
                value={form.password}
                onChange={(e) =>
                  setForm((f) => ({ ...f, password: e.target.value }))
                }
              />
            </FormField>
          )}
          <FormField label="Rôle">
            <Select
              value={form.roles}
              onChange={(e) =>
                setForm((f) => ({ ...f, roles: e.target.value }))
              }
            >
              <option value="ROLE_CAISSIER">Caissier</option>
              <option value="ROLE_BOUTIQUE_ADMIN">Admin boutique</option>
            </Select>
          </FormField>
        </form>
      </Modal>

      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer"
        message={`Supprimer l'accès de "${deleteTarget?.email}" ?`}
        confirmLabel="Supprimer"
        danger
      />
    </div>
  );
}
