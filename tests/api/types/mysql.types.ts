export interface MySqlDatabase {
  database: string;
  created_at?: string;
}

export interface MySqlUser {
  user: string;
  created_at?: string;
}

export interface MySqlPrivileges {
  database: string;
  user: string;
  privileges: string;
}
