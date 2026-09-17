import jwt from 'jsonwebtoken';

export interface JWTPayload {
  userId: number;
  companyId: number;
  email: string;
  role: string;
  iat?: number;
  exp?: number;
}

export interface JWTService {
  generateToken(payload: Omit<JWTPayload, 'iat' | 'exp'>): string;
  generateRefreshToken(payload: Omit<JWTPayload, 'iat' | 'exp'>): string;
  verifyToken(token: string): JWTPayload;
  verifyRefreshToken(token: string): JWTPayload;
  decodeToken(token: string): JWTPayload | null;
}

class JWTManager implements JWTService {
  private secret: string;
  private refreshSecret: string;
  private expiry: string;
  private refreshExpiry: string;

  constructor() {
    this.secret = process.env.JWT_SECRET || 'your_super_secret_jwt_key_change_in_production';
    this.refreshSecret = process.env.JWT_REFRESH_SECRET || 'your_super_secret_refresh_key';
    this.expiry = process.env.JWT_EXPIRY || '7d';
    this.refreshExpiry = process.env.JWT_REFRESH_EXPIRY || '30d';
  }

  generateToken(payload: Omit<JWTPayload, 'iat' | 'exp'>): string {
    return jwt.sign(payload, this.secret, {
      expiresIn: this.expiry as any,
      algorithm: 'HS256',
    });
  }

  generateRefreshToken(payload: Omit<JWTPayload, 'iat' | 'exp'>): string {
    return jwt.sign(payload, this.refreshSecret, {
      expiresIn: this.refreshExpiry as any,
      algorithm: 'HS256',
    });
  }

  verifyToken(token: string): JWTPayload {
    try {
      return jwt.verify(token, this.secret, {
        algorithms: ['HS256'],
      }) as JWTPayload;
    } catch (error) {
      throw new Error(`Token verification failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }

  verifyRefreshToken(token: string): JWTPayload {
    try {
      return jwt.verify(token, this.refreshSecret, {
        algorithms: ['HS256'],
      }) as JWTPayload;
    } catch (error) {
      throw new Error(`Refresh token verification failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }

  decodeToken(token: string): JWTPayload | null {
    try {
      return jwt.decode(token) as JWTPayload | null;
    } catch {
      return null;
    }
  }
}

export const jwtService = new JWTManager();
