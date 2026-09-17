"""Transport service for business logic."""
from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session
from sqlalchemy import and_
from app.models import Transport
from app.schemas import TransportCreate, TransportUpdate
from app.events import publisher
from config.logging_config import logger


class TransportService:
    """Service for transport operations."""

    def __init__(self, db: Session):
        """Initialize service with database session."""
        self.db = db

    def create_transport(
        self,
        company_id: int,
        transport_data: TransportCreate
    ) -> Transport:
        """
        Create a new transport record.

        Args:
            company_id: Company ID for multi-tenancy
            transport_data: Transport creation data

        Returns:
            Created Transport object
        """
        try:
            # Check for duplicate tracking number within company
            existing = self.db.query(Transport).filter(
                and_(
                    Transport.company_id == company_id,
                    Transport.tracking_number == transport_data.tracking_number
                )
            ).first()

            if existing:
                logger.warning(
                    f"Duplicate tracking number: {transport_data.tracking_number} "
                    f"for company {company_id}"
                )
                raise ValueError(
                    f"Tracking number {transport_data.tracking_number} already exists"
                )

            # Create transport record
            transport = Transport(
                company_id=company_id,
                **transport_data.model_dump()
            )

            self.db.add(transport)
            self.db.commit()
            self.db.refresh(transport)

            logger.info(
                f"Created transport {transport.id} "
                f"for company {company_id}"
            )

            # Publish event
            publisher.publish_created(
                transport.id,
                company_id,
                transport_data.model_dump()
            )

            return transport

        except ValueError:
            raise
        except Exception as e:
            self.db.rollback()
            logger.error(f"Error creating transport: {str(e)}")
            raise

    def get_transport(self, company_id: int, transport_id: int) -> Optional[Transport]:
        """
        Get transport by ID with company isolation.

        Args:
            company_id: Company ID for isolation
            transport_id: Transport ID

        Returns:
            Transport object or None
        """
        transport = self.db.query(Transport).filter(
            and_(
                Transport.id == transport_id,
                Transport.company_id == company_id
            )
        ).first()

        if not transport:
            logger.warning(
                f"Transport {transport_id} not found for company {company_id}"
            )

        return transport

    def list_transports(
        self,
        company_id: int,
        skip: int = 0,
        limit: int = 100,
        status: Optional[str] = None,
        is_active: Optional[bool] = True
    ) -> tuple[List[Transport], int]:
        """
        List transports with filtering.

        Args:
            company_id: Company ID for isolation
            skip: Number of records to skip
            limit: Maximum records to return
            status: Optional status filter
            is_active: Filter by active status

        Returns:
            Tuple of (transports list, total count)
        """
        query = self.db.query(Transport).filter(
            Transport.company_id == company_id
        )

        if is_active is not None:
            query = query.filter(Transport.is_active == is_active)

        if status:
            query = query.filter(Transport.status == status)

        total = query.count()
        transports = query.offset(skip).limit(limit).all()

        logger.info(
            f"Listed {len(transports)} transports for company {company_id} "
            f"(total: {total})"
        )

        return transports, total

    def update_transport(
        self,
        company_id: int,
        transport_id: int,
        update_data: TransportUpdate
    ) -> Optional[Transport]:
        """
        Update transport record.

        Args:
            company_id: Company ID for isolation
            transport_id: Transport ID to update
            update_data: Update data

        Returns:
            Updated Transport object or None
        """
        try:
            transport = self.get_transport(company_id, transport_id)
            if not transport:
                return None

            old_status = transport.status
            old_data = transport.to_dict()

            # Update fields
            update_dict = update_data.model_dump(exclude_unset=True)
            for field, value in update_dict.items():
                setattr(transport, field, value)

            self.db.commit()
            self.db.refresh(transport)

            logger.info(
                f"Updated transport {transport_id} for company {company_id}"
            )

            # Publish event
            publisher.publish_updated(company_id, transport_id, update_dict)

            # Publish status change event if status changed
            if old_status != transport.status:
                publisher.publish_status_changed(
                    transport_id,
                    company_id,
                    old_status,
                    transport.status
                )

            return transport

        except Exception as e:
            self.db.rollback()
            logger.error(f"Error updating transport {transport_id}: {str(e)}")
            raise

    def delete_transport(
        self,
        company_id: int,
        transport_id: int,
        soft_delete: bool = True
    ) -> bool:
        """
        Delete transport record (soft or hard delete).

        Args:
            company_id: Company ID for isolation
            transport_id: Transport ID to delete
            soft_delete: If True, marks as inactive; if False, hard delete

        Returns:
            True if deleted, False if not found
        """
        try:
            transport = self.get_transport(company_id, transport_id)
            if not transport:
                return False

            if soft_delete:
                transport.is_active = False
                self.db.commit()
                logger.info(
                    f"Soft deleted transport {transport_id} for company {company_id}"
                )
            else:
                self.db.delete(transport)
                self.db.commit()
                logger.info(
                    f"Hard deleted transport {transport_id} for company {company_id}"
                )

            # Publish event
            publisher.publish_deleted(transport_id, company_id)

            return True

        except Exception as e:
            self.db.rollback()
            logger.error(f"Error deleting transport {transport_id}: {str(e)}")
            raise

    def get_statistics(self, company_id: int) -> Dict[str, Any]:
        """
        Get transport statistics for a company.

        Args:
            company_id: Company ID

        Returns:
            Dictionary with statistics
        """
        try:
            query_base = self.db.query(Transport).filter(
                Transport.company_id == company_id,
                Transport.is_active == True
            )

            total = query_base.count()
            by_status = {}
            for status in ["pending", "in_transit", "delivered", "cancelled"]:
                count = query_base.filter(Transport.status == status).count()
                by_status[status] = count

            total_cost = sum(
                t.actual_cost or t.estimated_cost
                for t in query_base.all()
            )

            stats = {
                "total_transports": total,
                "by_status": by_status,
                "total_cost": total_cost,
            }

            logger.info(f"Generated statistics for company {company_id}")
            return stats

        except Exception as e:
            logger.error(f"Error generating statistics: {str(e)}")
            raise
