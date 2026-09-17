import jwt from 'jsonwebtoken';

const JWT_SECRET = process.env.JWT_SECRET || 'your-secret-key';
const JWT_EXPIRY = process.env.JWT_EXPIRY || '24h';

export interface JwtPayload {
  userId: string;
  companyId: string;
  email?: string;
}

export const generateToken = (payload: JwtPayload): string => {
  return jwt.sign(payload, JWT_SECRET, {
    expiresIn: JWT_EXPIRY,
  });
};

export const verifyToken = (token: string): JwtPayload => {
  try {
    const decoded = jwt.verify(token, JWT_SECRET) as JwtPayload;
    return decoded;
  } catch (error) {
    throw new Error('Invalid or expired token');
  }
};

export const decodeToken = (token: string): JwtPayload | null => {
  const decoded = jwt.decode(token) as JwtPayload | null;
  return decoded;
};
